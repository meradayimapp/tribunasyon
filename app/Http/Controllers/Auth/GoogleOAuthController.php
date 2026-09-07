<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleOAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors(['email' => 'Google ile giriş tamamlanamadı. Lütfen tekrar deneyin.']);
        }

        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $googleAttributes = $googleUser->getRaw();

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('login')->withErrors(['email' => 'Google hesabından geçerli bir e-posta adresi alınamadı.']);
        }

        if (array_key_exists('email_verified', $googleAttributes) && ! filter_var($googleAttributes['email_verified'], FILTER_VALIDATE_BOOL)) {
            return redirect()->route('login')->withErrors(['email' => 'Google hesabındaki e-posta adresi doğrulanmamış.']);
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        $isNewUser = $user === null;

        if ($user && ! $user->isActive()) {
            return redirect()->route('login')->withErrors(['email' => 'Bu hesap askıya alınmış.']);
        }

        if (! $user) {
            $user = new User([
                'name' => mb_substr(trim((string) $googleUser->getName()) ?: 'Google Kullanıcısı', 0, 100),
                'username' => $this->uniqueUsername($email),
                'email' => $email,
                'password' => Str::random(64),
                'role' => UserRole::Member,
                'status' => UserStatus::Active,
            ]);
            $user->email_verified_at = now();
            $user->save();
            event(new Registered($user));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return $isNewUser
            ? redirect()->route('onboarding.teams.edit')
            : redirect()->intended(route('home'));
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
}
