<?php

namespace App\Services\Football;

use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CompetitionPageService
{
    public function matches(FootballCompetition $competition): Collection
    {
        return FootballMatch::query()
            ->where('competition_id', $competition->id)
            ->with([
                'competition:id,provider_league_id,name,display_name',
                'homeTeam.team:id,name,short_name,slug,logo,primary_color,status,deleted_at',
                'awayTeam.team:id,name,short_name,slug,logo,primary_color,status,deleted_at',
            ])
            ->orderBy('kickoff_at')
            ->orderBy('id')
            ->get();
    }

    public function teams(Collection $matches): Collection
    {
        $ids = $matches
            ->flatMap(fn (FootballMatch $match): array => [$match->home_football_team_id, $match->away_football_team_id])
            ->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return FootballTeam::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->with('team:id,name,short_name,slug,logo,primary_color,status,deleted_at')
            ->get()
            ->sortBy(fn (FootballTeam $team): string => mb_strtolower($team->resolved_name, 'UTF-8'))
            ->values();
    }

    public function groups(Collection $matches, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(FootballMatch::DISPLAY_TIMEZONE);
        $today = $now->toDateString();

        return [
            'past' => $matches->filter(fn (FootballMatch $match): bool => $match->kickoffInDisplayTimezone()->toDateString() < $today)->values(),
            'today' => $matches->filter(fn (FootballMatch $match): bool => $match->kickoffInDisplayTimezone()->toDateString() === $today)->values(),
            'future' => $matches->filter(fn (FootballMatch $match): bool => $match->kickoffInDisplayTimezone()->toDateString() > $today)->values(),
        ];
    }

    public function turkeyProviderTeamId(FootballCompetition $competition, Collection $teams): ?string
    {
        if ($competition->provider_league_id !== FootballCompetition::NATIONS_LEAGUE_PROVIDER_ID) {
            return null;
        }

        $providerId = trim((string) config(
            'services.live_football_api.nations_league_turkey_team_id',
            FootballTeam::TURKEY_NATIONAL_PROVIDER_ID,
        ));

        if ($providerId === '') {
            $providerId = FootballTeam::TURKEY_NATIONAL_PROVIDER_ID;
        }

        return $providerId !== '' && $teams->contains(
            fn (FootballTeam $team): bool => $team->provider_team_id === $providerId
        ) ? $providerId : null;
    }

    public function turkeyMatches(Collection $matches, Collection $teams, FootballCompetition $competition): Collection
    {
        $providerId = $this->turkeyProviderTeamId($competition, $teams);

        if ($providerId === null) {
            return collect();
        }

        $teamId = $teams->firstWhere('provider_team_id', $providerId)?->id;

        return $matches->filter(fn (FootballMatch $match): bool => $match->home_football_team_id === $teamId || $match->away_football_team_id === $teamId
        )->values();
    }
}
