@extends('layouts.auth')
@section('title', 'Giriş yap')
@section('content')
<x-auth-panel :image-url="$authImageUrl" variant="login">
    <div class="eyebrow">Tekrar hoş geldin</div>
    <h1 class="auth-title">Tribüne dön</h1>
    <p class="auth-lead">Futbol konuş, paylaş, taraftarlarınla buluş.</p>

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="email">E-posta</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="E-posta adresini gir" autocomplete="email" autofocus required class="form-control @error('email') is-invalid @enderror">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3" x-data="{ visible: false }">
            <div class="d-flex justify-content-between align-items-center gap-3">
                <label class="form-label" for="password">Şifre</label>
                <a class="auth-inline-link" href="{{ route('password.request') }}">Şifremi unuttum</a>
            </div>
            <div class="auth-password-field">
                <input id="password" :type="visible ? 'text' : 'password'" name="password" placeholder="Şifreni gir" autocomplete="current-password" required class="form-control @error('password') is-invalid @enderror">
                <button type="button" @click="visible = ! visible" :aria-label="visible ? 'Şifreyi gizle' : 'Şifreyi göster'" x-text="visible ? 'Gizle' : 'Göster'"></button>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
            <label class="form-check-label" for="remember">Beni hatırla</label>
        </div>
        <button class="btn btn-primary auth-primary-action w-100" type="submit">Giriş yap</button>
    </form>

    <div class="auth-divider"><span>veya</span></div>
    <a class="btn auth-google-button w-100" href="{{ route('auth.google.redirect') }}"><x-google-icon /> Google ile devam et</a>
    <p class="auth-switch">Hesabın yok mu? <a href="{{ route('register') }}">Kayıt ol</a></p>
</x-auth-panel>
@endsection
