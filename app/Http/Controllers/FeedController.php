<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function __invoke(): View
    {
        return view('feed', ['suggestedTeams' => Team::active()->withCount('followers')->orderByDesc('followers_count')->limit(4)->get()]);
    }
}
