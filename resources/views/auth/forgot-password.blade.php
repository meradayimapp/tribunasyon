@extends('layouts.app')
@section('title', 'Şifremi unuttum')
@section('content')
<div class="auth-wrap"><div class="auth-card"><h1 class="h3 fw-bold">Şifreni yenile</h1><p class="muted mb-4">E-posta adresine güvenli bir sıfırlama bağlantısı göndereceğiz.</p><form method="POST" action="{{ route('password.email') }}">@csrf<div class="mb-4"><label class="form-label">E-posta</label><input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><button class="btn btn-primary w-100">Bağlantı gönder</button></form></div></div>
@endsection
