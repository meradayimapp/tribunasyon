<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PendingGoogleRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use RuntimeException;
use Throwable;

class GoogleAuthController extends Controller
{
    private const FAILURE_MESSAGE = 'Google ile giriş tamamlanamadı. Lütfen tekrar deneyin.';

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $googleId = trim((string) $googleUser->getId());
            $email = Str::lower(trim((string) $googleUser->getEmail()));
            $emailVerified = ($googleUser->getRaw()['email_verified'] ?? null) === true;

            if ($googleId === '' || mb_strlen($googleId) > 255 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->failed($request);
            }

            $user = User::query()->where('google_id', $googleId)->first();

            if (! $user) {
                if (! $emailVerified) {
                    return $this->failed($request);
                }

                $user = $this->linkExistingUser($googleId, $email, $this->avatarUrl($googleUser));
            }

            if ($user) {
                PendingGoogleRegistration::forget($request);

                if (! $user->isActive()) {
                    return redirect()->route('login')->withErrors(['email' => 'Bu hesap askıya alınmış.']);
                }

                Auth::login($user, true);
                $request->session()->regenerate();

                return redirect()->intended(route('home'));
            }

            PendingGoogleRegistration::put($request, [
                'google_id' => $googleId,
                'email' => $email,
                'name' => mb_substr(trim((string) $googleUser->getName()) ?: 'Google Kullanıcısı', 0, 100),
                'avatar_url' => $this->avatarUrl($googleUser),
            ]);

            return redirect()->route('auth.google.username.create');
        } catch (Throwable $exception) {
            PendingGoogleRegistration::forget($request);
            Log::warning('Google OAuth callback failed.', [
                'exception' => $exception::class,
            ]);

            return $this->failed($request);
        }
    }

    private function linkExistingUser(string $googleId, string $email, ?string $avatarUrl): ?User
    {
        return DB::transaction(function () use ($googleId, $email, $avatarUrl): ?User {
            $user = User::query()->where('google_id', $googleId)->lockForUpdate()->first();

            if ($user) {
                return $user;
            }

            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();

            if (! $user) {
                return null;
            }

            if ($user->google_id !== null && $user->google_id !== $googleId) {
                throw new RuntimeException('Google account cannot be linked.');
            }

            if ($user->isActive()) {
                $user->forceFill([
                    'google_id' => $googleId,
                    'google_avatar_url' => $avatarUrl,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            }

            return $user;
        });
    }

    private function avatarUrl(GoogleUser $googleUser): ?string
    {
        $avatar = trim((string) $googleUser->getAvatar());

        if ($avatar === ''
            || mb_strlen($avatar) > 2048
            || ! filter_var($avatar, FILTER_VALIDATE_URL)
            || parse_url($avatar, PHP_URL_SCHEME) !== 'https') {
            return null;
        }

        return $avatar;
    }

    private function failed(Request $request): RedirectResponse
    {
        PendingGoogleRegistration::forget($request);

        return redirect()->route('login')->withErrors(['email' => self::FAILURE_MESSAGE]);
    }
}
