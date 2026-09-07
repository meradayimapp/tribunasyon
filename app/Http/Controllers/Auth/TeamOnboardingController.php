<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamOnboardingController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.onboarding-teams', [
            'teams' => Team::active()->ordered()->get(),
            'favoriteTeamId' => $request->user()->favorite_team_id,
            'selectedTeamIds' => $request->user()->followedTeams()->pluck('teams.id')->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $activeTeamExists = Rule::exists('teams', 'id')->where(fn ($query) => $query
            ->where('status', 'active')
            ->whereNull('deleted_at'));
        $data = $request->validate([
            'favorite_team_id' => ['nullable', $activeTeamExists],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'distinct', $activeTeamExists],
        ]);

        $favoriteTeamId = isset($data['favorite_team_id']) ? (int) $data['favorite_team_id'] : null;
        $followedTeamIds = collect($data['team_ids'] ?? [])->map(fn ($id) => (int) $id);

        if ($favoriteTeamId) {
            $followedTeamIds->push($favoriteTeamId);
        }

        DB::transaction(function () use ($request, $favoriteTeamId, $followedTeamIds): void {
            $request->user()->update(['favorite_team_id' => $favoriteTeamId]);
            $request->user()->followedTeams()->sync($followedTeamIds->unique()->values()->all());
        });

        return redirect()->route('home')->with('success', 'Takım tercihlerin kaydedildi.');
    }

    public function skip(): RedirectResponse
    {
        return redirect()->route('home');
    }
}
