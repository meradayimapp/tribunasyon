<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\User;
use App\Rules\Username;
use App\Support\PendingGoogleRegistration;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class GoogleRegistrationController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $pending = PendingGoogleRegistration::get($request);

        if (! $pending) {
            return redirect()->route('register')->withErrors([
                'google' => 'Google kayıt oturumunun süresi doldu. Lütfen tekrar deneyin.',
            ]);
        }

        return view('auth.google-username', [
            'pending' => $pending,
            'authImageUrl' => SiteSetting::current()->mediaUrl('register_image_path'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pending = PendingGoogleRegistration::get($request);

        if (! $pending) {
            return redirect()->route('register')->withErrors([
                'google' => 'Google kayıt oturumunun süresi doldu. Lütfen tekrar deneyin.',
            ]);
        }

        $request->merge(['username' => Username::normalize($request->input('username'))]);
        $data = $request->validate(['username' => ['required', new Username]]);

        try {
            [$user, $created] = DB::transaction(function () use ($pending, $data): array {
                $user = User::query()->where('google_id', $pending['google_id'])->lockForUpdate()->first();

                if ($user) {
                    return [$user, false];
                }

                $user = User::query()->whereRaw('LOWER(email) = ?', [$pending['email']])->lockForUpdate()->first();

                if ($user) {
                    if (! $user->isActive() || ($user->google_id !== null && $user->google_id !== $pending['google_id'])) {
                        throw new RuntimeException('Google account cannot be linked.');
                    }

                    $user->forceFill([
                        'google_id' => $pending['google_id'],
                        'google_avatar_url' => $pending['avatar_url'],
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ])->save();

                    return [$user, false];
                }

                if (User::query()->whereRaw('LOWER(username) = ?', [$data['username']])->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['username' => 'Bu kullanıcı adı daha önce alınmış.']);
                }

                $user = new User([
                    'name' => $pending['name'],
                    'username' => $data['username'],
                    'email' => $pending['email'],
                    'password' => Hash::make(Str::random(64)),
                    'password_set_at' => null,
                    'google_id' => $pending['google_id'],
                    'google_avatar_url' => $pending['avatar_url'],
                    'role' => UserRole::Member,
                    'status' => UserStatus::Active,
                ]);
                $user->email_verified_at = now();
                $user->save();

                return [$user, true];
            });
        } catch (QueryException) {
            $user = User::query()->where('google_id', $pending['google_id'])->first();

            if (! $user) {
                throw ValidationException::withMessages([
                    'username' => 'Kayıt tamamlanamadı. Kullanıcı adı alınmış olabilir; lütfen başka bir kullanıcı adı deneyin.',
                ]);
            }

            $created = false;
        } catch (RuntimeException) {
            PendingGoogleRegistration::forget($request);

            return redirect()->route('login')->withErrors([
                'email' => 'Google ile kayıt tamamlanamadı. Lütfen tekrar deneyin.',
            ]);
        }

        if (! $user->isActive()) {
            PendingGoogleRegistration::forget($request);

            return redirect()->route('login')->withErrors(['email' => 'Bu hesap askıya alınmış.']);
        }

        if ($created) {
            event(new Registered($user));
        }

        PendingGoogleRegistration::forget($request);
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended($created ? route('onboarding.teams.edit') : route('home'));
    }
}
