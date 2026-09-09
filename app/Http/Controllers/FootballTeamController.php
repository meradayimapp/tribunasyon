<?php

namespace App\Http\Controllers;

use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class FootballTeamController extends Controller
{
    public function __invoke(FootballTeam $footballTeam): View|RedirectResponse
    {
        $footballTeam->loadMissing('team:id,name,slug,logo,primary_color');

        if ($footballTeam->team_id !== null) {
            if ($footballTeam->team !== null) {
                return redirect()->route('teams.show', $footballTeam->team);
            }
        }

        $now = Carbon::now('UTC');
        $relations = [
            'competition:id,name,display_name,sort_order',
            'homeTeam.team:id,name,slug,logo,primary_color',
            'awayTeam.team:id,name,slug,logo,primary_color',
        ];
        $teamMatches = fn (): Builder => FootballMatch::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('home_football_team_id', $footballTeam->id)
                ->orWhere('away_football_team_id', $footballTeam->id))
            ->whereHas('competition', fn (Builder $query): Builder => $query->active());

        $upcoming = $teamMatches()
            ->where('kickoff_at', '>=', $now->format('Y-m-d H:i:s'))
            ->with($relations)
            ->orderBy('kickoff_at')
            ->limit(10)
            ->get();
        $recent = $teamMatches()
            ->where('kickoff_at', '<', $now->format('Y-m-d H:i:s'))
            ->with($relations)
            ->latest('kickoff_at')
            ->limit(10)
            ->get();
        $competitions = FootballCompetition::query()
            ->active()
            ->whereHas('matches', fn (Builder $query): Builder => $query
                ->where('home_football_team_id', $footballTeam->id)
                ->orWhere('away_football_team_id', $footballTeam->id))
            ->ordered()
            ->get(['id', 'name', 'display_name']);

        return view('football-teams.show', compact('footballTeam', 'competitions', 'upcoming', 'recent'));
    }
}
