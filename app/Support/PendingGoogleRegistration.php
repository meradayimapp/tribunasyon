<?php

namespace App\Support;

use Illuminate\Http\Request;

class PendingGoogleRegistration
{
    private const SESSION_KEY = 'auth.google.pending_registration';

    private const LIFETIME_MINUTES = 10;

    /**
     * @param  array{google_id: string, email: string, name: string, avatar_url: ?string}  $data
     */
    public static function put(Request $request, array $data): void
    {
        $request->session()->put(self::SESSION_KEY, $data + [
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES)->timestamp,
        ]);
    }

    /**
     * @return array{google_id: string, email: string, name: string, avatar_url: ?string, expires_at: int}|null
     */
    public static function get(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending)
            || ! isset($pending['google_id'], $pending['email'], $pending['name'], $pending['expires_at'])
            || (int) $pending['expires_at'] < now()->timestamp
            || trim((string) $pending['google_id']) === ''
            || ! filter_var($pending['email'], FILTER_VALIDATE_EMAIL)) {
            self::forget($request);

            return null;
        }

        return $pending;
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
