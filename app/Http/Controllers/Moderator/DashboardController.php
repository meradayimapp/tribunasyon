<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('moderator.dashboard', ['teams' => $request->user()->moderatedTeams()->withCount(['posts', 'followers'])->orderBy('name')->get()]);
    }
}
