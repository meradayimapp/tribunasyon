<?php

namespace App\Services;

use App\Contracts\FootballDataService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MockFootballDataService implements FootballDataService
{
    public function matchesForDate(Carbon $date): Collection
    {
        return collect([
            ['id' => 'mock-1', 'league' => 'Süper Lig', 'home' => 'Fenerbahçe', 'away' => 'Trabzonspor', 'home_score' => null, 'away_score' => null, 'status' => 'scheduled', 'kickoff_at' => $date->copy()->setTime(20, 0)],
            ['id' => 'mock-2', 'league' => 'Süper Lig', 'home' => 'Galatasaray', 'away' => 'Beşiktaş', 'home_score' => 2, 'away_score' => 1, 'status' => 'finished', 'kickoff_at' => $date->copy()->setTime(17, 0)],
            ['id' => 'mock-3', 'league' => 'Hazırlık', 'home' => 'Mavi Takım', 'away' => 'Beyaz Takım', 'home_score' => 1, 'away_score' => 1, 'status' => 'live', 'kickoff_at' => $date->copy()->setTime(19, 0)],
        ]);
    }
}
