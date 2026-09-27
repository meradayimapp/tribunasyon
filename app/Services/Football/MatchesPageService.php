<?php

namespace App\Services\Football;

use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MatchesPageService
{
    public const STATUSES = ['all', 'live', 'upcoming', 'finished'];

    public function competitions(): Collection
    {
        return FootballCompetition::query()
            ->active()
            ->featured()
            ->where('provider', LiveFootballApiService::PROVIDER)
            ->ordered()
            ->get([
                'id', 'provider', 'provider_league_id', 'name', 'display_name', 'slug',
                'country', 'provider_logo_url', 'logo_path', 'current_season', 'sort_order',
            ]);
    }

    public function matches(
        CarbonImmutable $date,
        string $status = 'all',
        ?FootballCompetition $competition = null,
    ): Collection {
        $start = $date->startOfDay()->utc();
        $end = $date->addDay()->startOfDay()->utc();

        return FootballMatch::query()
            ->indexable()
            ->where('football_matches.kickoff_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('football_matches.kickoff_at', '<', $end->format('Y-m-d H:i:s'))
            ->whereHas('competition', fn ($query) => $query
                ->featured()
                ->where('provider', LiveFootballApiService::PROVIDER))
            ->when($competition !== null, fn ($query) => $query
                ->where('football_matches.competition_id', $competition->id))
            ->forMatchCenterStatus($status)
            ->with([
                'competition:id,provider,provider_league_id,name,display_name,slug,country,provider_logo_url,logo_path,current_season,sort_order',
                'homeTeam:id,provider_team_id,provider_name,display_name,provider_logo_url,country,is_active,team_id',
                'homeTeam.team:id,name,short_name,slug,logo,primary_color,status,deleted_at',
                'awayTeam:id,provider_team_id,provider_name,display_name,provider_logo_url,country,is_active,team_id',
                'awayTeam.team:id,name,short_name,slug,logo,primary_color,status,deleted_at',
            ])
            ->join('football_competitions', 'football_competitions.id', '=', 'football_matches.competition_id')
            ->orderBy('football_competitions.sort_order')
            ->orderBy('football_matches.kickoff_at')
            ->orderBy('football_matches.id')
            ->select('football_matches.*')
            ->get();
    }

    public function groups(Collection $matches): Collection
    {
        return $matches->groupBy('competition_id')->values();
    }
}
