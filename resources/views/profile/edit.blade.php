@extends('layouts.app')
@section('title', 'Profili düzenle')
@section('mobile-title', 'Profili düzenle')
@section('mobile-back', route('profile.show', $user))
@section('content')
<div class="auth-wrap mt-0"><div class="auth-card"><h1 class="h4 fw-bold mb-4">Profili düzenle</h1>
<form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">@csrf @method('PUT')
    <div class="mb-3"><label class="form-label">Ad</label><input class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name',$user->name) }}">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="mb-3"><label class="form-label">Kullanıcı adı</label><input class="form-control @error('username') is-invalid @enderror" name="username" value="{{ old('username',$user->username) }}">@error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="mb-3"><label class="form-label">E-posta</label><input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email',$user->email) }}">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="mb-3"><label class="form-label">Kısa bio</label><textarea class="form-control" name="bio" rows="3" maxlength="280">{{ old('bio',$user->bio) }}</textarea></div>
    <div class="mb-3"><label class="form-label">Favori takım</label><select class="form-select" name="favorite_team_id">@foreach($teams as $team)<option value="{{ $team->id }}" @selected(old('favorite_team_id',$user->favorite_team_id)==$team->id)>{{ $team->name }}</option>@endforeach</select></div>
    <div class="mb-4"><label class="form-label">Profil fotoğrafı</label><input type="file" class="form-control" name="avatar" accept="image/jpeg,image/png,image/webp"></div>
    <button class="btn btn-primary w-100">Değişiklikleri kaydet</button>
</form></div>
<div class="auth-card mt-3"><h2 class="h5 fw-bold mb-3">Şifreyi değiştir</h2><form method="POST" action="{{ route('profile.password') }}">@csrf @method('PUT')<div class="mb-3"><input type="password" class="form-control" name="current_password" placeholder="Mevcut şifre"></div><div class="mb-3"><input type="password" class="form-control" name="password" placeholder="Yeni şifre"></div><div class="mb-3"><input type="password" class="form-control" name="password_confirmation" placeholder="Yeni şifre tekrar"></div><button class="btn btn-outline-dark">Şifreyi güncelle</button></form></div></div>
@endsection
