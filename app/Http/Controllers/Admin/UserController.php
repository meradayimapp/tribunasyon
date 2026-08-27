<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()->with('favoriteTeam')->withCount('moderatedTeams')
            ->when($request->string('q')->isNotEmpty(), fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%')->orWhere('username', 'like', '%'.$request->q.'%')))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user, 'teams' => Team::active()->orderBy('name')->get()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user) && $request->input('status') === UserStatus::Suspended->value, 422, 'Kendi hesabınızı askıya alamazsınız.');
        $data = $request->validate(['role' => ['required', Rule::enum(UserRole::class)], 'status' => ['required', Rule::enum(UserStatus::class)], 'favorite_team_id' => ['nullable', 'exists:teams,id']]);
        $user->update($data);
        if ($user->role !== UserRole::Moderator) {
            $user->moderatedTeams()->detach();
        }

        return redirect()->route('admin.users.index')->with('success', 'Kullanıcı güncellendi.');
    }
}
