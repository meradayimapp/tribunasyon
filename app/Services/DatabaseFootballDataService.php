<?php

namespace App\Services;

use App\Contracts\FootballDataService;
use App\Models\FootballMatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DatabaseFootballDataService implements FootballDataService
{
    public function matchesForDate(Carbon $date): Collection
    {
        $timezone = 'Europe/Istanbul';
        $start = $date->copy()->setTimezone($timezone)->startOfDay()->utc();
        $end = $date->copy()->setTimezone($timezone)->endOfDay()->utc();

        return FootballMatch::query()
            ->whereBetween('kickoff_at', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')])
            ->whereHas('competition', fn ($query) => $query->active())
            ->with([
                'competition:id,name,display_name,sort_order',
                'homeTeam.team:id,name,slug,logo,primary_color',
                'awayTeam.team:id,name,slug,logo,primary_color',
            ])
            ->join('football_competitions', 'football_competitions.id', '=', 'football_matches.competition_id')
            ->orderBy('football_competitions.sort_order')
            ->orderBy('football_matches.kickoff_at')
            ->orderBy('football_matches.id')
            ->select('football_matches.*')
            ->get();
    }
}
