<?php

namespace App\Http\Controllers;

use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        return view('teams.index', ['teams' => Team::active()->ordered()->withCount(['followers', 'posts' => fn ($query) => $query->published()])->get()]);
    }

    public function show(Team $team): View
    {
        $this->loadPublicProfile($team);

        return view('teams.show', compact('team'));
    }

    public function fixtures(Request $request, Team $team): View
    {
        $this->loadPublicProfile($team);

        $footballTeamIds = $team->footballTeams()->pluck('id');
        $hasFootballTeam = $footballTeamIds->isNotEmpty();
        $competitions = collect();
        $nextMatch = null;
        $matches = collect();
        $view = $request->query('view') === 'results' ? 'results' : 'upcoming';
        $selectedCompetitionId = null;

        if ($hasFootballTeam) {
            $competitions = FootballCompetition::query()
                ->active()
                ->whereHas('matches', fn (Builder $query): Builder => $query->where(
                    fn (Builder $matches): Builder => $this->forFootballTeams($matches, $footballTeamIds->all())
                ))
                ->ordered()
                ->get(['id', 'name', 'display_name', 'slug']);

            $requestedCompetitionId = $request->integer('competition');
            if ($requestedCompetitionId > 0 && $competitions->contains('id', $requestedCompetitionId)) {
                $selectedCompetitionId = $requestedCompetitionId;
            }

            $now = Carbon::now('UTC')->format('Y-m-d H:i:s');

            if ($view === 'results') {
                $matches = $this->teamMatches($footballTeamIds->all(), $selectedCompetitionId)
                    ->where('status', 'finished')
                    ->latest('kickoff_at')
                    ->limit(15)
                    ->get();
            } else {
                $upcoming = fn (): Builder => $this->teamMatches($footballTeamIds->all(), $selectedCompetitionId)
                    ->where('kickoff_at', '>=', $now)
                    ->whereNotIn('status', FootballMatch::TERMINAL_STATUSES)
                    ->orderBy('kickoff_at');

                $nextMatch = $upcoming()->first();
                $matches = $upcoming()->limit(15)->get();
            }
        }

        return view('teams.fixtures', compact(
            'team',
            'hasFootballTeam',
            'competitions',
            'selectedCompetitionId',
            'view',
            'nextMatch',
            'matches',
        ));
    }

    public function players(Request $request, Team $team): View
    {
        $this->loadPublicProfile($team);

        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $players = $team->players()
            ->active()
            ->select(['id', 'name', 'slug', 'photo_path', 'position', 'shirt_number', 'current_team_id', 'sort_order', 'status'])
            ->ordered()
            ->get();

        if ($search !== '') {
            $players = $players
                ->filter(fn ($player): bool => mb_stripos($player->name, $search, 0, 'UTF-8') !== false)
                ->values();
        }

        return view('teams.players', compact('team', 'players', 'search'));
    }

    private function loadPublicProfile(Team $team): void
    {
        abort_unless($team->status->value === 'active', 404);
        $team->load('organization')->loadCount('followers');
    }

    private function teamMatches(array $footballTeamIds, ?int $competitionId): Builder
    {
        return FootballMatch::query()
            ->when($competitionId !== null, fn (Builder $query): Builder => $query->where('competition_id', $competitionId))
            ->where(fn (Builder $query): Builder => $this->forFootballTeams($query, $footballTeamIds))
            ->whereHas('competition', fn (Builder $query): Builder => $query->active())
            ->with([
                'competition:id,name,display_name,slug',
                'homeTeam.team:id,name,slug,logo,primary_color',
                'awayTeam.team:id,name,slug,logo,primary_color',
            ]);
    }

    private function forFootballTeams(Builder $query, array $footballTeamIds): Builder
    {
        return $query
            ->whereIn('home_football_team_id', $footballTeamIds)
            ->orWhereIn('away_football_team_id', $footballTeamIds);
    }
}
