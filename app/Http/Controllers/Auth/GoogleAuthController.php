<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
                return $this->failed();
            }

            $user = User::query()->where('google_id', $googleId)->first();
            $isNewUser = false;

            if (! $user) {
                if (! $emailVerified) {
                    return $this->failed();
                }

                [$user, $isNewUser] = $this->linkOrCreateUser($googleUser, $googleId, $email);
            }
        } catch (Throwable $exception) {
            Log::warning('Google OAuth callback failed.', [
                'exception' => $exception::class,
            ]);

            return $this->failed();
        }

        if (! $user->isActive()) {
            return redirect()->route('login')->withErrors(['email' => 'Bu hesap askıya alınmış.']);
        }

        if ($isNewUser) {
            event(new Registered($user));
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return $isNewUser
            ? redirect()->route('onboarding.teams.edit')
            : redirect()->intended(route('home'));
    }

    /**
     * @return array{0: User, 1: bool}
     */
    private function linkOrCreateUser(GoogleUser $googleUser, string $googleId, string $email): array
    {
        try {
            return DB::transaction(function () use ($googleUser, $googleId, $email): array {
                $user = User::query()->where('google_id', $googleId)->lockForUpdate()->first();

                if ($user) {
                    return [$user, false];
                }

                $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();

                if ($user) {
                    if ($user->google_id !== null && $user->google_id !== $googleId) {
                        throw new RuntimeException('Google account cannot be linked.');
                    }

                    if (! $user->isActive()) {
                        return [$user, false];
                    }

                    $user->forceFill([
                        'google_id' => $googleId,
                        'google_avatar_url' => $this->avatarUrl($googleUser),
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ])->save();

                    return [$user, false];
                }

                $user = new User([
                    'name' => mb_substr(trim((string) $googleUser->getName()) ?: 'Google Kullanıcısı', 0, 100),
                    'username' => $this->uniqueUsername($email),
                    'email' => $email,
                    'password' => Hash::make(Str::random(64)),
                    'google_id' => $googleId,
                    'google_avatar_url' => $this->avatarUrl($googleUser),
                    'role' => UserRole::Member,
                    'status' => UserStatus::Active,
                ]);
                $user->email_verified_at = now();
                $user->save();

                return [$user, true];
            });
        } catch (QueryException $exception) {
            $user = User::query()->where('google_id', $googleId)->first();

            if ($user) {
                return [$user, false];
            }

            throw $exception;
        }
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

    private function uniqueUsername(string $email): string
    {
        $base = Str::slug(Str::before($email, '@'), '_');
        $base = mb_substr($base ?: 'taraftar', 0, 36);
        $base = mb_strlen($base) >= 3 ? $base : 'taraftar_'.$base;
        $candidate = $base;
        $suffix = 1;

        while (User::query()->where('username', $candidate)->exists()) {
            $suffixText = '_'.$suffix++;
            $candidate = mb_substr($base, 0, 40 - mb_strlen($suffixText)).$suffixText;
        }

        return $candidate;
    }

    private function failed(): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => self::FAILURE_MESSAGE]);
    }
}
