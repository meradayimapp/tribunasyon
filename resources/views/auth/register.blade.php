@extends('layouts.auth')
@section('title', 'Kayıt ol')
@section('content')
<x-auth-panel :image-url="$authImageUrl" variant="register">
    <div class="eyebrow">Hesabını oluştur</div>
    <h1 class="auth-title">Aramıza katıl</h1>
    <p class="auth-lead">Futbolun yeni sosyal ağında yerini al.</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="name">Ad Soyad</label>
            <input id="name" name="name" value="{{ old('name') }}" maxlength="100" autocomplete="name" required class="form-control @error('name') is-invalid @enderror">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="username">Kullanıcı adı</label>
            <div class="input-group">
                <span class="input-group-text">@</span>
                <input id="username" name="username" value="{{ old('username') }}" minlength="3" maxlength="40" autocomplete="username" required class="form-control @error('username') is-invalid @enderror">
                @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="email">E-posta</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required class="form-control @error('email') is-invalid @enderror">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="row g-3 mb-4" x-data="{ passwordVisible: false, confirmationVisible: false }">
            <div class="col-sm-6">
                <label class="form-label" for="password">Şifre</label>
                <div class="auth-password-field">
                    <input id="password" :type="passwordVisible ? 'text' : 'password'" name="password" autocomplete="new-password" required class="form-control @error('password') is-invalid @enderror">
                    <button type="button" @click="passwordVisible = ! passwordVisible" :aria-label="passwordVisible ? 'Şifreyi gizle' : 'Şifreyi göster'" x-text="passwordVisible ? 'Gizle' : 'Göster'"></button>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="password-confirmation">Şifre tekrar</label>
                <div class="auth-password-field">
                    <input id="password-confirmation" :type="confirmationVisible ? 'text' : 'password'" name="password_confirmation" autocomplete="new-password" required class="form-control">
                    <button type="button" @click="confirmationVisible = ! confirmationVisible" :aria-label="confirmationVisible ? 'Şifreyi gizle' : 'Şifreyi göster'" x-text="confirmationVisible ? 'Gizle' : 'Göster'"></button>
                </div>
            </div>
        </div>
        <button class="btn btn-primary auth-primary-action w-100" type="submit">Kayıt ol</button>
    </form>

    <div class="auth-divider"><span>veya</span></div>
    <a class="btn auth-google-button w-100" href="{{ route('auth.google.redirect') }}"><x-google-icon /> Google ile devam et</a>
    <p class="auth-switch">Zaten hesabın var mı? <a href="{{ route('login') }}">Giriş yap</a></p>
</x-auth-panel>
@endsection
