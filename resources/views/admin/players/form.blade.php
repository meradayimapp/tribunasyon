@extends('layouts.app')
@section('title', $player->exists ? 'Oyuncuyu düzenle' : 'Oyuncu ekle')
@section('mobile-title', $player->exists ? 'Oyuncuyu düzenle' : 'Oyuncu ekle')
@section('content')
<div class="panel-shell">
    <div class="eyebrow">Yönetim</div><h1 class="page-title">{{ $player->exists ? 'Oyuncuyu düzenle' : 'Yeni oyuncu' }}</h1><x-panel-nav />
    <form class="surface" method="POST" enctype="multipart/form-data" action="{{ $player->exists ? route('admin.players.update', $player) : route('admin.players.store') }}">
        @csrf @if($player->exists)@method('PUT')@endif
        <div class="row g-4">
            <div class="col-md-6"><x-admin.image-upload name="photo_file" label="Oyuncu fotoğrafı" :current-url="$player->photoUrl()" remove-name="remove_photo" accept="image/jpeg,image/png,image/webp" help="JPG, PNG veya WebP, en fazla 5 MB." /></div>
            <div class="col-md-6"><x-admin.image-upload name="cover_file" label="Kapak görseli" :current-url="$player->coverImageUrl()" remove-name="remove_cover" accept="image/jpeg,image/png,image/webp" help="JPG, PNG veya WebP, en fazla 5 MB." /></div>
            <div class="col-md-7"><label class="form-label" for="name">Ad soyad</label><input id="name" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $player->name) }}" required maxlength="120">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-5"><label class="form-label" for="slug">Slug</label><input id="slug" class="form-control @error('slug') is-invalid @enderror" name="slug" value="{{ old('slug', $player->slug) }}" maxlength="140" placeholder="Boşsa otomatik oluşur">@error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label" for="current_team_id">Mevcut takım</label><select id="current_team_id" class="form-select @error('current_team_id') is-invalid @enderror" name="current_team_id"><option value="">Serbest oyuncu</option>@foreach($teams as $team)<option value="{{ $team->id }}" @selected((string) old('current_team_id', $player->current_team_id) === (string) $team->id)>{{ $team->name }}</option>@endforeach</select>@error('current_team_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label" for="position">Pozisyon</label><input id="position" class="form-control" name="position" value="{{ old('position', $player->position) }}" maxlength="80"></div>
            <div class="col-md-2"><label class="form-label" for="shirt_number">Forma no</label><input id="shirt_number" class="form-control" type="number" min="0" max="99" name="shirt_number" value="{{ old('shirt_number', $player->shirt_number) }}"></div>
            <div class="col-md-2"><label class="form-label" for="birth_date">Doğum tarihi</label><input id="birth_date" class="form-control" type="date" name="birth_date" value="{{ old('birth_date', $player->birth_date?->format('Y-m-d')) }}"></div>
            <div class="col-md-5"><label class="form-label" for="national_team_name">Milli takım</label><input id="national_team_name" class="form-control" name="national_team_name" value="{{ old('national_team_name', $player->national_team_name) }}" maxlength="100"></div>
            <div class="col-md-2"><label class="form-label" for="national_team_code">Ülke kodu</label><input id="national_team_code" class="form-control" name="national_team_code" value="{{ old('national_team_code', $player->national_team_code) }}" maxlength="2" placeholder="TR"></div>
            <div class="col-md-3"><label class="form-label" for="market_value_amount">Piyasa değeri</label><input id="market_value_amount" class="form-control" type="number" min="0" name="market_value_amount" value="{{ old('market_value_amount', $player->market_value_amount) }}" placeholder="75000000"></div>
            <div class="col-md-2"><label class="form-label" for="market_value_currency">Para birimi</label><input id="market_value_currency" class="form-control" name="market_value_currency" value="{{ old('market_value_currency', $player->market_value_currency) }}" maxlength="3" placeholder="EUR"></div>
            <div class="col-12"><label class="form-label" for="bio">Kısa biyografi</label><textarea id="bio" class="form-control" name="bio" rows="5" maxlength="3000">{{ old('bio', $player->bio) }}</textarea></div>
            <div class="col-md-4"><label class="form-label" for="status">Durum</label><select id="status" class="form-select" name="status" required><option value="active" @selected(old('status', $player->status?->value ?? 'active') === 'active')>Aktif</option><option value="inactive" @selected(old('status', $player->status?->value) === 'inactive')>Pasif</option></select></div>
            <div class="col-md-4"><label class="form-label" for="sort_order">Sıralama</label><input id="sort_order" class="form-control" type="number" min="0" name="sort_order" value="{{ old('sort_order', $player->sort_order ?? 0) }}"></div>
            @if($errors->any())<div class="col-12"><div class="alert alert-danger mb-0">Lütfen işaretli alanları kontrol edin.</div></div>@endif
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Kaydet</button><a class="btn btn-light" href="{{ route('admin.players.index') }}">Vazgeç</a></div>
        </div>
    </form>
</div>
@endsection
