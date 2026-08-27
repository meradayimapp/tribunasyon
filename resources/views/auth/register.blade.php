@extends('layouts.app')
@section('title', 'Kayıt ol')
@section('content')
<div class="auth-wrap"><div class="auth-card"><div class="eyebrow">Topluluğa katıl</div><h1 class="h3 fw-bold mb-1">Tribündeki yerini al</h1><p class="muted mb-4">Favori takımını seç, akışın hemen oluşsun.</p>
<form method="POST" action="{{ route('register') }}">@csrf
    <div class="mb-3"><label class="form-label">Ad</label><input name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="mb-3"><label class="form-label">Kullanıcı adı</label><div class="input-group"><span class="input-group-text">@</span><input name="username" value="{{ old('username') }}" class="form-control @error('username') is-invalid @enderror"></div>@error('username')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
    <div class="mb-3"><label class="form-label">E-posta</label><input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="mb-3"><label class="form-label">Favori takım</label><select name="favorite_team_id" class="form-select @error('favorite_team_id') is-invalid @enderror"><option value="">Takımını seç</option>@foreach($teams as $team)<option value="{{ $team->id }}" @selected(old('favorite_team_id')==$team->id)>{{ $team->name }}</option>@endforeach</select>@error('favorite_team_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="mb-3"><label class="form-label">Şifre</label><input type="password" name="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="mb-4"><label class="form-label">Şifre tekrar</label><input type="password" name="password_confirmation" autocomplete="new-password" class="form-control"></div>
    <button class="btn btn-primary w-100">Hesabımı oluştur</button>
</form><p class="text-center small muted mt-4 mb-0">Zaten üye misin? <a class="text-primary fw-bold" href="{{ route('login') }}">Giriş yap</a></p></div></div>
@endsection
