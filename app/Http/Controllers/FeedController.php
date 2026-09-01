<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function __invoke(): View
    {
        return view('feed', [
            'suggestedTeams' => Team::active()->ordered()->withCount('followers')->limit(4)->get(),
        ]);
    }
}
