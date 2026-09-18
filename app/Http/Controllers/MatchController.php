<?php

namespace App\Http\Controllers;

use App\Contracts\FootballDataService;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use App\Services\Football\LeagueStandingsService;
use App\Services\Football\LiveFootballApiService;
use App\Services\Football\MatchLineupPresenter;
use App\Services\Football\MatchSupplementService;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MatchController extends Controller
{
    public function index(Request $request, FootballDataService $service): View
    {
        $date = Carbon::today(FootballMatch::DISPLAY_TIMEZONE);
        $competitions = $service->featuredCompetitions();
        $requestedCompetitionId = (string) $request->query('competition', '');
        $selectedCompetition = $competitions->firstWhere('provider_league_id', $requestedCompetitionId)
            ?? $competitions->first();
        $matches = $selectedCompetition === null
            ? collect()
            : $service->matchesForDate($date)
                ->filter(fn (FootballMatch $match): bool => $match->competition->provider_league_id === $selectedCompetition->provider_league_id)
                ->values();

        return view('matches.index', [
            'date' => $date,
            'competitions' => $competitions,
            'selectedCompetition' => $selectedCompetition,
            'liveMatches' => $matches->filter->is_live->values(),
            'upcomingMatches' => $matches->reject->is_live->reject->isCompleted()->values(),
            'finishedMatches' => $matches->filter->isCompleted()->values(),
        ]);
    }

    public function todayState(FootballDataService $service): JsonResponse
    {
        $matches = $service->matchesForDate(Carbon::today(FootballMatch::DISPLAY_TIMEZONE));

        return response()->json(['matches' => $service->scoreRibbonPayload($matches)])
            ->header('Cache-Control', 'no-store, private');
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
}
