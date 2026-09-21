<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Services\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    public function destroy(Request $request, MediaStorageService $media): RedirectResponse
    {
        $user = $request->user();

        if ($user->role !== UserRole::Member) {
            return back()->withErrors([
                'account' => 'Yönetici veya moderatör hesabı silinemez. Önce rolünüzün bir yönetici tarafından üye olarak değiştirilmesi gerekir.',
            ], 'deleteAccount');
        }

        $rules = ['confirmation' => ['accepted']];

        if ($user->hasUsablePassword()) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $request->validateWithBag('deleteAccount', $rules);
        $avatarPath = $user->avatar_path;
        $oldEmail = $user->email;
        $userId = $user->id;

        DB::transaction(function () use ($user, $userId, $oldEmail): void {
            $user->likes()->delete();
            $user->followedTeams()->detach();
            $user->followedPlayers()->detach();
            $user->moderatedTeams()->detach();

            DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
            DB::table('sessions')->where('user_id', $userId)->delete();

            $user->forceFill([
                'name' => 'Silinmiş kullanıcı',
                'username' => 'silinmis_kullanici_'.$userId,
                'email' => 'silinmis-'.$userId.'@anonim.tribunasyon.invalid',
                'email_verified_at' => null,
                'password' => Hash::make(Str::random(64)),
                'password_set_at' => null,
                'avatar_path' => null,
                'google_id' => null,
                'google_avatar_url' => null,
                'bio' => null,
                'favorite_team_id' => null,
                'remember_token' => null,
                'status' => UserStatus::Suspended,
                'anonymized_at' => now(),
            ])->save();
        });

        if ($media->isManagedPath($avatarPath, 'avatars')) {
            $media->delete($avatarPath);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Hesabınız ve kişisel bilgileriniz silindi.');
    }
}
