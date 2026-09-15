<?php

namespace App\Contracts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface FootballDataService
{
    public function featuredCompetitions(): Collection;

    public function matchesForDate(Carbon $date): Collection;

    public function scoreRibbonPayload(Collection $matches): array;
}
