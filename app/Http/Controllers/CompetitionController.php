<?php

namespace App\Http\Controllers;

use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use App\Services\Football\CompetitionPageService;
use App\Services\Football\LeagueStandingsService;
use App\Services\Football\LiveFootballApiService;
use App\Services\SeoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompetitionController extends Controller
{
    private const TABS = ['genel-bakis', 'fikstur', 'puan-durumu', 'takimlar'];

    private const FIXTURE_FILTERS = ['tumu', 'yaklasan', 'tamamlanan'];

    public function show(
        Request $request,
        string $competition,
        CompetitionPageService $page,
        LeagueStandingsService $standingsService,
        SeoService $seoService,
    ): View {
        $competition = FootballCompetition::query()->active()->where('slug', $competition)->firstOrFail();
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'genel-bakis';
        $matches = $page->matches($competition);
        $displaySeason = $competition->current_season ?: $matches->pluck('season')->filter()->first();
        $teams = $page->teams($matches);
        $turkeyProviderTeamId = $page->turkeyProviderTeamId($competition, $teams);
        $now = CarbonImmutable::now(FootballMatch::DISPLAY_TIMEZONE);
        $today = $now->toDateString();
        $overviewMatches = $page->overviewMatches($matches, $now);
        $fixtureFilter = in_array($request->query('filter'), self::FIXTURE_FILTERS, true)
            ? $request->query('filter')
            : 'tumu';
        $fixtureMatches = $page->filterFixtures($matches, $fixtureFilter);

        $standings = $tab === 'puan-durumu'
            && $competition->provider === LiveFootballApiService::PROVIDER
            ? $standingsService->forLeague($competition->provider_league_id, $competition->current_season)
            : null;
        $tables = collect($standings['tables'] ?? [])->filter(
            fn (mixed $table): bool => is_array($table) && ($table['rows'] ?? []) !== []
        )->values();
        $localTeams = $this->standingsTeams($tables);
        $visibleMatches = match ($tab) {
            'genel-bakis' => $overviewMatches,
            'fikstur' => $fixtureMatches,
            default => collect(),
        };
        $pollingMatches = $visibleMatches->filter->shouldPollLiveState()->values();

        return view('competitions.show', [
            'competition' => $competition,
            'displaySeason' => $displaySeason,
            'tab' => $tab,
            'matches' => $matches,
            'teams' => $teams,
            'overviewMatches' => $overviewMatches,
            'overviewDateGroups' => $page->dateGroups($overviewMatches),
            'overviewHasTodayMatches' => $overviewMatches->contains(
                fn (FootballMatch $match): bool => $match->kickoffInDisplayTimezone()->toDateString() === $today
            ),
            'fixtureFilter' => $fixtureFilter,
            'fixtureMatches' => $fixtureMatches,
            'fixtureDateGroups' => $page->dateGroups($fixtureMatches),
            'turkeyProviderTeamId' => $turkeyProviderTeamId,
            'tables' => $tables,
            'standingsSeason' => $standings['season'] ?? null,
            'localTeams' => $localTeams,
            'pollingMatches' => $pollingMatches,
            'seo' => $seoService->competition($competition, $displaySeason),
        ]);
    }

    public function state(Request $request, string $competition): JsonResponse
    {
        $competition = FootballCompetition::query()->active()->where('slug', $competition)->firstOrFail();
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['integer', Rule::exists('football_matches', 'id')->where('competition_id', $competition->id)],
        ]);

        $matches = FootballMatch::query()
            ->where('competition_id', $competition->id)
            ->whereIn('id', array_unique($validated['ids']))
            ->get(['id', 'kickoff_at', 'status', 'state', 'status_display', 'is_live', 'home_score', 'away_score', 'live_minute', 'last_synced_at']);

        return response()->json(['matches' => $matches->mapWithKeys(fn (FootballMatch $match): array => [
            $match->id => $match->statePayload(),
        ])])->header('Cache-Control', 'no-store, private');
    }

    private function standingsTeams(Collection $tables): Collection
    {
        $providerIds = $tables->flatMap(fn (array $table): array => $table['rows'])
            ->pluck('provider_team_id')->filter()->unique()->values();

        return $providerIds->isEmpty() ? collect() : FootballTeam::query()
            ->where('provider', LiveFootballApiService::PROVIDER)
            ->whereIn('provider_team_id', $providerIds)
            ->with('team:id,name,short_name,slug,logo,primary_color,status,deleted_at')
            ->get()
            ->keyBy('provider_team_id');
    }
}
