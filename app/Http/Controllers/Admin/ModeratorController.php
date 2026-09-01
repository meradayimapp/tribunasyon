<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModeratorController extends Controller
{
    public function index(): View
    {
        return view('admin.moderators.index', ['moderators' => User::where('role', UserRole::Moderator)->with('moderatedTeams')->orderBy('name')->get()]);
    }

    public function edit(User $user): View
    {
        abort_unless($user->isModerator(), 404);

        return view('admin.moderators.edit', ['user' => $user->load('moderatedTeams'), 'teams' => Team::active()->ordered()->get()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isModerator(), 404);
        $data = $request->validate([
            'teams' => ['array'],
            'teams.*' => ['integer', Rule::exists('teams', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'))],
        ]);
        $user->moderatedTeams()->sync($data['teams'] ?? []);

        return redirect()->route('admin.moderators.index')->with('success', 'Takım atamaları güncellendi.');
    }
}
