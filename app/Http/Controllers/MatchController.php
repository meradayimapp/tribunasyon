<?php

namespace App\Http\Controllers;

use App\Contracts\FootballDataService;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use App\Services\Football\LeagueStandingsService;
use App\Services\Football\LiveFootballApiService;
use App\Services\Football\MatchesPageService;
use App\Services\Football\MatchLineupPresenter;
use App\Services\Football\MatchSupplementService;
use App\Services\SeoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MatchController extends Controller
{
    public function index(
        Request $request,
        MatchesPageService $page,
        SeoService $seoService,
    ): View|RedirectResponse {
        $today = CarbonImmutable::today(FootballMatch::DISPLAY_TIMEZONE);
        $status = in_array($request->query('status'), MatchesPageService::STATUSES, true)
            ? (string) $request->query('status')
            : 'all';
        $competitionSlug = trim((string) $request->query('competition', 'all')) ?: 'all';
        $date = $this->requestedDate($request->query('date'));

        if ($date === null) {
            return redirect()->route('matches.index', array_filter([
                'date' => $today->toDateString(),
                'status' => $status !== 'all' ? $status : null,
                'competition' => $competitionSlug !== 'all' ? $competitionSlug : null,
            ]));
        }

        $competitions = $page->competitions();
        $selectedCompetition = $competitionSlug === 'all'
            ? null
            : $competitions->firstWhere('slug', $competitionSlug);
        $competitionSlug = $selectedCompetition?->slug ?? 'all';
        $matches = $page->matches($date, $status, $selectedCompetition);
        $pollingMatches = $matches->filter->is_live->values();
        $initialStates = $pollingMatches->mapWithKeys(fn (FootballMatch $match): array => [
            $match->id => $this->listingStatePayload($match),
        ]);

        return view('matches.index', [
            'date' => $date,
            'today' => $today,
            'status' => $status,
            'competitions' => $competitions,
            'selectedCompetition' => $selectedCompetition,
            'competitionSlug' => $competitionSlug,
            'matches' => $matches,
            'matchGroups' => $page->groups($matches),
            'initialStates' => $initialStates,
            'seo' => $seoService->defaults($request, 'Maçlar'),
        ]);
    }

    public function todayState(FootballDataService $service): JsonResponse
    {
        $matches = $service->matchesForDate(Carbon::today(FootballMatch::DISPLAY_TIMEZONE));

        return response()->json(['matches' => $service->scoreRibbonPayload($matches)])
            ->header('Cache-Control', 'no-store, private');
    }

    public function indexState(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $matches = FootballMatch::query()
            ->indexable()
            ->whereIn('football_matches.id', array_unique($validated['ids']))
            ->whereHas('competition', fn ($query) => $query
                ->featured()
                ->where('provider', LiveFootballApiService::PROVIDER))
            ->get([
                'id', 'kickoff_at', 'status', 'state', 'status_display', 'is_live',
                'home_score', 'away_score', 'live_minute', 'last_synced_at',
            ]);

        return response()->json(['matches' => $matches->mapWithKeys(fn (FootballMatch $match): array => [
            $match->id => $this->listingStatePayload($match),
        ])])->header('Cache-Control', 'no-store, private');
    }

    public function show(
        FootballMatch $footballMatch,
        SeoService $seoService,
        LeagueStandingsService $standingsService,
        MatchSupplementService $supplements,
        MatchLineupPresenter $lineupPresenter,
    ): View {
        $footballMatch->load([
            'competition:id,name,display_name,provider,provider_league_id,is_active',
            'homeTeam.team:id,name,slug,logo,primary_color,status,deleted_at',
            'awayTeam.team:id,name,slug,logo,primary_color,status,deleted_at',
        ]);

        $leagueId = $footballMatch->competition?->provider === LiveFootballApiService::PROVIDER
            ? $footballMatch->competition->provider_league_id : null;
        $standings = filled($leagueId) ? $standingsService->forLeague($leagueId) : null;
        $tables = collect($standings['tables'] ?? [])
            ->filter(fn (mixed $table): bool => is_array($table) && ($table['rows'] ?? []) !== [])
            ->values();
        $teamIds = $tables->flatMap(fn (array $table): array => $table['rows'])
            ->pluck('provider_team_id')->unique()->values();
        $localTeams = $teamIds->isEmpty() ? collect() : FootballTeam::query()
            ->where('provider', LiveFootballApiService::PROVIDER)
            ->where('is_active', true)
            ->whereIn('provider_team_id', $teamIds)
            ->whereHas('team', fn ($query) => $query->active())
            ->with('team:id,name,slug,logo')
            ->get(['id', 'provider_team_id', 'team_id'])
            ->mapWithKeys(fn (FootballTeam $team): array => [
                $team->provider_team_id => [
                    'logo' => $team->team->logoUrl(),
                    'url' => route('teams.show', $team->team),
                ],
            ]);

        return view('matches.show', [
            'match' => $footballMatch,
            'seo' => $seoService->match($footballMatch),
            'tables' => $tables,
            'standingsSeason' => $standings['season'] ?? null,
            'localTeams' => $localTeams,
            'currentProviderTeamIds' => [
                $footballMatch->homeTeam->provider_team_id,
                $footballMatch->awayTeam->provider_team_id,
            ],
            'h2h' => $supplements->headToHead($footballMatch),
            'injuries' => $supplements->injuries($footballMatch),
            'presentedLineups' => $lineupPresenter->present($footballMatch->lineups),
        ]);
    }

    public function state(FootballMatch $footballMatch, MatchLineupPresenter $lineupPresenter): JsonResponse
    {
        $match = FootballMatch::query()
            ->select([
                'id', 'kickoff_at', 'status', 'state', 'status_display', 'is_live',
                'home_score', 'away_score', 'live_minute', 'last_synced_at',
                'live_events', 'live_details_synced_at',
                'match_stats', 'lineups', 'lineup_is_projected', 'lineup_synced_at',
            ])
            ->findOrFail($footballMatch->id);

        $payload = $match->statePayload();
        $payload['lineups'] = $lineupPresenter->present($payload['lineups'] ?? null);

        return response()->json($payload)
            ->header('Cache-Control', 'no-store, private');
    }

    private function requestedDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return CarbonImmutable::today(FootballMatch::DISPLAY_TIMEZONE);
        }

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, FootballMatch::DISPLAY_TIMEZONE);
        } catch (\Throwable) {
            return null;
        }

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    /** @return array<string, mixed> */
    private function listingStatePayload(FootballMatch $match): array
    {
        return [
            'status' => $match->status,
            'is_live' => $match->is_live,
            'is_half_time' => $match->isHalfTime(),
            'is_finished' => $match->isFinished(),
            'score' => ['home' => $match->home_score, 'away' => $match->away_score],
            'minute' => $match->displayMinute(),
            'status_display' => $match->stateStatusLabel(),
            'polling_active' => $match->is_live && ! $match->isFinished(),
        ];
    }
}
