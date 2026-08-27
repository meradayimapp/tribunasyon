<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Services\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(User $user): View
    {
        $user->load(['favoriteTeam', 'followedTeams' => fn ($query) => $query->active()->orderBy('name')]);

        return view('profile.show', compact('user'));
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user(), 'teams' => Team::active()->orderBy('name')->get()]);
    }

    public function update(Request $request, MediaStorageService $media): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:40', 'unique:users,username,'.$user->id],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email,'.$user->id],
            'bio' => ['nullable', 'string', 'max:280'],
            'favorite_team_id' => ['required', 'exists:teams,id'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $media->replace($user->avatar_path, $request->file('avatar'), 'avatars');
        }

        unset($data['avatar']);
        $user->update($data);
        $user->followedTeams()->syncWithoutDetaching([$data['favorite_team_id']]);

        return redirect()->route('profile.show', $user)->with('success', 'Profilin güncellendi.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', Password::defaults()]]);
        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Şifren güncellendi.');
    }
}
