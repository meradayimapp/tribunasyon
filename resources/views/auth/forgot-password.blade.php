@extends('layouts.auth')
@section('title', 'Şifreni yenile')
@section('content')
<div class="auth-wrap">
    <div class="auth-card">
        <h1 class="h3 fw-bold">Şifreni yenile</h1>
        <p class="muted mb-4">E-posta adresini veya kullanıcı adını gir; hesabın eşleşirse güvenli bir sıfırlama bağlantısı göndereceğiz.</p>
        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="mb-4">
                <label class="form-label" for="identifier">E-posta veya kullanıcı adı</label>
                <input id="identifier" name="identifier" value="{{ old('identifier') }}" autocomplete="username" required class="form-control @error('identifier') is-invalid @enderror">
                @error('identifier')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button class="btn btn-primary w-100">Bağlantı gönder</button>
        </form>
    </div>
</div>
@endsection
