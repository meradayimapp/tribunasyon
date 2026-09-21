<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class Username implements ValidationRule
{
    private const RESERVED = [
        'admin',
        'administrator',
        'moderator',
        'mod',
        'tribunasyon',
        'tribun',
        'tribün',
        'support',
        'destek',
        'help',
        'yardim',
        'api',
        'auth',
        'login',
        'giris',
        'register',
        'kayit',
        'logout',
        'cikis',
        'profile',
        'profil',
        'settings',
        'ayarlar',
        'sifremi-unuttum',
        'sifre-sifirla',
        'takim',
        'takimlar',
        'maclar',
        'oyuncu',
        'oyuncular',
        'ara',
    ];

    public function __construct(private readonly ?int $ignoreUserId = null) {}

    public static function normalize(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $username = self::normalize($value);

        if (mb_strlen($username) < 3 || mb_strlen($username) > 30) {
            $fail('Kullanıcı adı 3 ile 30 karakter arasında olmalıdır.');

            return;
        }

        if (preg_match('/\A[\pL\pN._-]+\z/u', $username) !== 1) {
            $fail('Kullanıcı adı yalnızca harf, rakam, alt çizgi, tire ve nokta içerebilir.');

            return;
        }

        if (in_array($username, self::RESERVED, true)) {
            $fail('Bu kullanıcı adı kullanılamaz.');

            return;
        }

        $query = DB::table('users')->whereRaw('LOWER(username) = ?', [$username]);

        if ($this->ignoreUserId !== null) {
            $query->where('id', '!=', $this->ignoreUserId);
        }

        if ($query->exists()) {
            $fail('Bu kullanıcı adı daha önce alınmış.');
        }
    }
}
