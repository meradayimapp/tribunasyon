<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Rules\Username;
use App\Services\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    public function show(User $user): View
    {
        abort_if($user->isAnonymized(), 404);

        $user->load(['favoriteTeam', 'followedTeams' => fn ($query) => $query->active()->ordered()]);

        return view('profile.show', compact('user'));
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user(), 'teams' => Team::active()->ordered()->get()]);
    }

    public function update(Request $request, MediaStorageService $media): RedirectResponse
    {
        $user = $request->user();
        $request->merge([
            'username' => Username::normalize($request->input('username')),
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', new Username($user->id)],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email,'.$user->id],
            'bio' => ['nullable', 'string', 'max:280'],
            'favorite_team_id' => ['required', Rule::exists('teams', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'))],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $oldAvatarPath = $user->avatar_path;
        $newAvatarPath = null;

        if ($request->hasFile('avatar')) {
            $newAvatarPath = $media->store($request->file('avatar'), 'avatars');
            $data['avatar_path'] = $newAvatarPath;
        }

        unset($data['avatar']);

        try {
            DB::transaction(function () use ($user, $data): void {
                $user->update($data);
                $user->followedTeams()->syncWithoutDetaching([$data['favorite_team_id']]);
            });
        } catch (Throwable $exception) {
            if ($newAvatarPath && $media->isManagedPath($newAvatarPath, 'avatars')) {
                $media->delete($newAvatarPath);
            }

            throw $exception;
        }

        if ($newAvatarPath && $media->isManagedPath($oldAvatarPath, 'avatars')) {
            $media->delete($oldAvatarPath);
        }

        return redirect()->route('profile.show', $user)->with('success', 'Profilin güncellendi.');
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();
        $hadUsablePassword = $user->hasUsablePassword();
        $rules = [
            'password' => ['required', 'confirmed', Password::defaults()],
        ];

        if ($user->hasUsablePassword()) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $data = $request->validate($rules);
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'password_set_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        return back()->with('success', $user->google_id && ! $hadUsablePassword
            ? 'Şifren belirlendi.'
            : 'Şifren güncellendi.');
    }
}
