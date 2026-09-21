<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Username;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    private const RESPONSE_MESSAGE = 'Eğer bu bilgilerle eşleşen bir hesap varsa şifre sıfırlama bağlantısı e-posta adresine gönderildi.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);
        $identifier = trim($data['identifier']);

        $user = str_contains($identifier, '@')
            ? User::query()->whereRaw('LOWER(email) = ?', [Str::lower($identifier)])->first()
            : User::query()->whereRaw('LOWER(username) = ?', [Username::normalize($identifier)])->first();

        if ($user && ! $user->isAnonymized()) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', self::RESPONSE_MESSAGE);
    }
}
