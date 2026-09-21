@extends('layouts.auth')
@section('title', 'Kullanıcı adını seç')
@section('content')
<x-auth-panel :image-url="$authImageUrl" variant="register">
    <div class="eyebrow">Google ile kayıt</div>
    <h1 class="auth-title">Kullanıcı adını seç</h1>
    <p class="auth-lead">Tribünasyon'da görünecek benzersiz kullanıcı adını belirle.</p>

    <form method="POST" action="{{ route('auth.google.username.store') }}">
        @csrf
        <div class="mb-4">
            <label class="form-label" for="username">Kullanıcı adı</label>
            <div class="input-group">
                <span class="input-group-text">@</span>
                <input id="username" name="username" value="{{ old('username') }}" minlength="3" maxlength="30" autocomplete="username" autofocus required class="form-control @error('username') is-invalid @enderror">
                @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-text">3-30 karakter; harf, rakam, alt çizgi, tire ve nokta kullanabilirsin.</div>
        </div>
        <button class="btn btn-primary auth-primary-action w-100" type="submit">Kaydı tamamla</button>
    </form>
</x-auth-panel>
@endsection
