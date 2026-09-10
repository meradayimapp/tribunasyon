<?php

namespace App\Http\Controllers;

use App\Contracts\FootballDataService;
use App\Models\FootballMatch;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MatchController extends Controller
{
    public function index(FootballDataService $service): View
    {
        $date = Carbon::today(FootballMatch::DISPLAY_TIMEZONE);

        return view('matches.index', ['date' => $date, 'matches' => $service->matchesForDate($date)]);
    }

    public function show(FootballMatch $footballMatch, SeoService $seoService): View
    {
        $footballMatch->load([
            'competition:id,name,display_name,is_active',
            'homeTeam.team:id,name,slug,logo,primary_color,status,deleted_at',
            'awayTeam.team:id,name,slug,logo,primary_color,status,deleted_at',
        ]);

        return view('matches.show', ['match' => $footballMatch, 'seo' => $seoService->match($footballMatch)]);
    }

    public function state(FootballMatch $footballMatch): JsonResponse
    {
        $match = FootballMatch::query()
            ->select([
                'id', 'status', 'state', 'status_display', 'is_live',
                'home_score', 'away_score', 'live_minute', 'last_synced_at',
                'live_events', 'live_details_synced_at',
            ])
            ->findOrFail($footballMatch->id);

        return response()->json($match->statePayload())
            ->header('Cache-Control', 'no-store, private');
    }
}
