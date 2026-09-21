@extends('layouts.app')
@section('title', 'Profili düzenle')
@section('mobile-title', 'Profili düzenle')
@section('mobile-back', route('profile.show', $user))
@section('content')
<div class="auth-wrap mt-0">
    <div class="auth-card">
        <h1 class="h4 fw-bold mb-4">Profili düzenle</h1>
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="mb-3"><label class="form-label">Ad</label><input class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $user->name) }}">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="mb-3"><label class="form-label">Kullanıcı adı</label><input class="form-control @error('username') is-invalid @enderror" name="username" maxlength="30" value="{{ old('username', $user->username) }}">@error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="mb-3"><label class="form-label">E-posta</label><input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email', $user->email) }}">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="mb-3"><label class="form-label">Kısa bio</label><textarea class="form-control" name="bio" rows="3" maxlength="280">{{ old('bio', $user->bio) }}</textarea></div>
            <div class="mb-3"><label class="form-label">Favori takım</label><select class="form-select" name="favorite_team_id">@foreach($teams as $team)<option value="{{ $team->id }}" @selected(old('favorite_team_id', $user->favorite_team_id) == $team->id)>{{ $team->name }}</option>@endforeach</select></div>
            <div class="mb-4">
                <label class="form-label">Profil fotoğrafı</label>
                <input type="file" class="form-control @error('avatar') is-invalid @enderror" name="avatar" accept="image/jpeg,image/png,image/webp">
                @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">JPG, PNG veya WebP; en fazla 5 MB.</div>
            </div>
            <button class="btn btn-primary w-100">Değişiklikleri kaydet</button>
        </form>
    </div>

    <div class="auth-card mt-3">
        <h2 class="h5 fw-bold mb-3">{{ $user->hasUsablePassword() ? 'Şifreyi değiştir' : 'Şifre belirle' }}</h2>
        @unless($user->hasUsablePassword())<p class="muted">Google hesabınla giriş yapmaya devam ederken e-posta ve şifreyle de giriş yapabilirsin.</p>@endunless
        <form method="POST" action="{{ route('profile.password') }}">
            @csrf
            @method('PUT')
            @if($user->hasUsablePassword())
                <div class="mb-3"><input type="password" class="form-control @error('current_password') is-invalid @enderror" name="current_password" autocomplete="current-password" placeholder="Mevcut şifre">@error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            @endif
            <div class="mb-3"><input type="password" class="form-control @error('password') is-invalid @enderror" name="password" autocomplete="new-password" placeholder="Yeni şifre">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="mb-3"><input type="password" class="form-control" name="password_confirmation" autocomplete="new-password" placeholder="Yeni şifre tekrar"></div>
            <button class="btn btn-outline-light">{{ $user->hasUsablePassword() ? 'Şifreyi güncelle' : 'Şifreyi belirle' }}</button>
        </form>
    </div>

    <div class="auth-card mt-3 border border-danger">
        <h2 class="h5 fw-bold text-danger mb-2">Hesabı Sil</h2>
        <p class="muted mb-3">Kişisel bilgileriniz kalıcı olarak anonimleştirilir. Yorum ve sohbet mesajları topluluk bütünlüğü için “Silinmiş kullanıcı” adıyla kalabilir.</p>
        @if($errors->deleteAccount->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->deleteAccount->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
        @endif
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">Hesabı Sil</button>
    </div>
</div>

<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="deleteAccountTitle">Hesabını kalıcı olarak sil</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button></div>
            <form method="POST" action="{{ route('account.destroy') }}">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <p>Bu işlem geri alınamaz. Devam etmek istediğini ikinci kez onayla.</p>
                    @if($user->hasUsablePassword())
                        <div class="mb-3"><label class="form-label">Mevcut şifre</label><input type="password" name="current_password" autocomplete="current-password" class="form-control @error('current_password', 'deleteAccount') is-invalid @enderror">@error('current_password', 'deleteAccount')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    @endif
                    <div class="form-check"><input class="form-check-input @error('confirmation', 'deleteAccount') is-invalid @enderror" type="checkbox" value="1" name="confirmation" id="delete-confirmation" required><label class="form-check-label" for="delete-confirmation">Hesabımın kalıcı olarak silineceğini anlıyorum.</label>@error('confirmation', 'deleteAccount')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button><button type="submit" class="btn btn-danger">Hesabımı sil</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
