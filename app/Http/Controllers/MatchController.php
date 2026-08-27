<?php

namespace App\Http\Controllers;

use App\Contracts\FootballDataService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MatchController extends Controller
{
    public function __invoke(FootballDataService $service): View
    {
        $date = Carbon::today();

        return view('matches.index', ['date' => $date, 'matches' => $service->matchesForDate($date)]);
    }
}
