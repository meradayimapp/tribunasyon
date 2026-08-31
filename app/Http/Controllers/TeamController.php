<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        return view('teams.index', ['teams' => Team::active()->withCount(['followers', 'posts' => fn ($query) => $query->published()])->orderBy('name')->get()]);
    }

    public function show(Team $team): View
    {
        abort_unless($team->status->value === 'active', 404);
        $team->load('organization')->loadCount('followers');

        return view('teams.show', compact('team'));
    }
}
