@extends('layouts.app')
@section('title', 'Yeni şifre')
@section('content')
<div class="auth-wrap"><div class="auth-card"><h1 class="h3 fw-bold mb-4">Yeni şifre oluştur</h1><form method="POST" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><div class="mb-3"><label class="form-label">E-posta</label><input type="email" name="email" value="{{ old('email',$email) }}" class="form-control"></div><div class="mb-3"><label class="form-label">Yeni şifre</label><input type="password" name="password" class="form-control"></div><div class="mb-4"><label class="form-label">Yeni şifre tekrar</label><input type="password" name="password_confirmation" class="form-control"></div><button class="btn btn-primary w-100">Şifreyi yenile</button></form></div></div>
@endsection
