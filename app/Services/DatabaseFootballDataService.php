<?php

namespace App\Services;

use App\Contracts\FootballDataService;
use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Services\Football\LiveFootballApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DatabaseFootballDataService implements FootballDataService
{
    private ?Collection $featuredCompetitions = null;

    /** @var array<string, Collection> */
    private array $matchesByDate = [];

    private ?Request $request = null;

    public function featuredCompetitions(): Collection
    {
        $this->beginRequest();

        return $this->featuredCompetitions ??= FootballCompetition::query()
            ->active()
            ->featured()
            ->where('provider', LiveFootballApiService::PROVIDER)
            ->ordered()
            ->get(['id', 'provider_league_id', 'name', 'display_name', 'slug', 'sort_order']);
    }

    public function matchesForDate(Carbon $date): Collection
    {
        $this->beginRequest();

        $localDate = $date->copy()->setTimezone(FootballMatch::DISPLAY_TIMEZONE);
        $cacheKey = $localDate->toDateString();

        if (isset($this->matchesByDate[$cacheKey])) {
            return $this->matchesByDate[$cacheKey];
        }

        $start = $localDate->copy()->startOfDay()->utc();
        $end = $localDate->copy()->addDay()->startOfDay()->utc();

        return $this->matchesByDate[$cacheKey] = FootballMatch::query()
            ->where('football_matches.kickoff_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('football_matches.kickoff_at', '<', $end->format('Y-m-d H:i:s'))
            ->whereHas('competition', fn ($query) => $query
                ->active()
                ->featured()
                ->where('provider', LiveFootballApiService::PROVIDER))
            ->with([
                'competition:id,provider_league_id,name,display_name,sort_order',
                'homeTeam.team:id,name,short_name,slug,logo,primary_color,status,deleted_at',
                'awayTeam.team:id,name,short_name,slug,logo,primary_color,status,deleted_at',
            ])
            ->join('football_competitions', 'football_competitions.id', '=', 'football_matches.competition_id')
            ->orderBy('football_competitions.sort_order')
            ->select('football_matches.*')
            ->get()
            ->sort($this->matchSorter(...))
            ->values();
    }

    public function scoreRibbonPayload(Collection $matches): array
    {
        return $matches->map(static fn (FootballMatch $match): array => [
            'id' => $match->id,
            'competition' => [
                'id' => $match->competition->provider_league_id,
                'name' => $match->competition->display_name ?: $match->competition->name,
            ],
            'home_team' => [
                'name' => $match->homeTeam->resolved_name,
                'short_name' => $match->homeTeam->team?->short_name ?: $match->homeTeam->resolved_name,
                'logo' => $match->homeTeam->logoUrl(),
            ],
            'away_team' => [
                'name' => $match->awayTeam->resolved_name,
                'short_name' => $match->awayTeam->team?->short_name ?: $match->awayTeam->resolved_name,
                'logo' => $match->awayTeam->logoUrl(),
            ],
            'home_score' => $match->home_score,
            'away_score' => $match->away_score,
            'status' => $match->status,
            'status_label' => $match->scoreRibbonStatus(),
            'is_live' => $match->is_live,
            'is_finished' => $match->isCompleted(),
            'kickoff' => $match->kickoffInDisplayTimezone()->toIso8601String(),
            'kickoff_time' => $match->kickoffTime(),
            'url' => route('matches.show', $match),
        ])->all();
    }

    private function matchSorter(FootballMatch $left, FootballMatch $right): int
    {
        $leftGroup = $this->sortGroup($left);
        $rightGroup = $this->sortGroup($right);

        if ($leftGroup !== $rightGroup) {
            return $leftGroup <=> $rightGroup;
        }

        $kickoffComparison = $left->kickoff_at <=> $right->kickoff_at;

        if ($kickoffComparison !== 0) {
            return $leftGroup === 2 ? -$kickoffComparison : $kickoffComparison;
        }

        return $left->id <=> $right->id;
    }

    private function sortGroup(FootballMatch $match): int
    {
        if ($match->is_live) {
            return 0;
        }

        return $match->isFinished() ? 2 : 1;
    }

    private function beginRequest(): void
    {
        $request = request();

        if ($this->request === $request) {
            return;
        }

        $this->request = $request;
        $this->featuredCompetitions = null;
        $this->matchesByDate = [];
    }
}
