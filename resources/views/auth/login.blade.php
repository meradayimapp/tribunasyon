@extends('layouts.app')
@section('title', 'Giriş yap')
@section('content')
<div class="auth-wrap"><div class="auth-card"><div class="eyebrow">Tekrar hoş geldin</div><h1 class="h3 fw-bold mb-4">Tribüne dön</h1>
<form method="POST" action="{{ route('login') }}">@csrf
    <div class="mb-3"><label class="form-label" for="email">E-posta</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" autofocus class="form-control @error('email') is-invalid @enderror">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="mb-3"><div class="d-flex justify-content-between"><label class="form-label" for="password">Şifre</label><a class="small text-primary" href="{{ route('password.request') }}">Şifremi unuttum</a></div><input id="password" type="password" name="password" autocomplete="current-password" class="form-control @error('password') is-invalid @enderror">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="form-check mb-4"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label" for="remember">Beni hatırla</label></div>
    <button class="btn btn-primary w-100" type="submit">Giriş yap</button>
</form><p class="text-center small muted mt-4 mb-0">Hesabın yok mu? <a class="text-primary fw-bold" href="{{ route('register') }}">Kayıt ol</a></p></div></div>
@endsection
