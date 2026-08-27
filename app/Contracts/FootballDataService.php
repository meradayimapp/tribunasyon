<?php

namespace App\Contracts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface FootballDataService
{
    public function matchesForDate(Carbon $date): Collection;
}
