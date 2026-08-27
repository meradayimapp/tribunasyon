@extends('layouts.app')
@section('title', $team->exists ? 'Takımı düzenle' : 'Takım oluştur')
@section('content')
@php
    $selectedOrganizationBadge = old('organization_badge', $team->organization_badge);
    $availableOrganizationBadgePaths = $organizationBadges->pluck('path');
@endphp
<div class="panel-shell">
    <div class="eyebrow">Yönetim</div>
    <h1 class="page-title">{{ $team->exists ? 'Takımı düzenle' : 'Yeni takım' }}</h1>
    <x-panel-nav />

    <form class="surface" method="POST" enctype="multipart/form-data" action="{{ $team->exists ? route('admin.teams.update', $team) : route('admin.teams.store') }}">
        @csrf
        @if($team->exists)@method('PUT')@endif

        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Takım adı</label><input class="form-control" name="name" value="{{ old('name', $team->name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Slug</label><input class="form-control" name="slug" value="{{ old('slug', $team->slug) }}" placeholder="Otomatik oluşturulur"></div>
            <div class="col-md-4"><label class="form-label">Kısa ad</label><input class="form-control" name="short_name" value="{{ old('short_name', $team->short_name) }}" maxlength="12" required></div>
            <div class="col-md-4"><label class="form-label">Ana renk</label><input type="color" class="form-control form-control-color w-100" name="primary_color" value="{{ old('primary_color', $team->primary_color ?: '#2357d8') }}"></div>
            <div class="col-md-4"><label class="form-label">İkincil renk</label><input type="color" class="form-control form-control-color w-100" name="secondary_color" value="{{ old('secondary_color', $team->secondary_color ?: '#ffffff') }}"></div>
            <div class="col-md-6"><label class="form-label">Logo</label><input type="file" class="form-control" name="logo_file" accept="image/jpeg,image/png,image/webp"></div>
            <div class="col-md-6"><label class="form-label">Kapak</label><input type="file" class="form-control" name="cover_file" accept="image/jpeg,image/png,image/webp"></div>

            <div class="col-12">
                <fieldset class="organization-badge-picker">
                    <legend class="form-label">Organizasyon rozeti</legend>
                    <div class="organization-badge-options">
                        <label class="organization-badge-option">
                            <input type="radio" name="organization_badge" value="" @checked(! $selectedOrganizationBadge || ! $availableOrganizationBadgePaths->contains($selectedOrganizationBadge))>
                            <span class="organization-badge-none"><i class="bi bi-slash-circle" aria-hidden="true"></i></span>
                            <span><strong>Organizasyon rozeti yok</strong><small>Takım adının yanında rozet gösterilmez.</small></span>
                        </label>
                        @foreach($organizationBadges as $badge)
                            <label class="organization-badge-option">
                                <input type="radio" name="organization_badge" value="{{ $badge['path'] }}" @checked($selectedOrganizationBadge === $badge['path'])>
                                <img src="{{ $badge['url'] }}" alt="" loading="lazy">
                                <span><strong>{{ $badge['label'] }}</strong><small>{{ basename($badge['path']) }}</small></span>
                            </label>
                        @endforeach
                    </div>
                    @if($organizationBadges->isEmpty())
                        <p class="organization-badge-help">Desteklenen organizasyon görseli bulunamadı.</p>
                    @endif
                </fieldset>
            </div>

            <div class="col-md-6"><label class="form-label">Durum</label><select class="form-select" name="status"><option value="active" @selected(old('status', $team->status?->value) === 'active')>Aktif</option><option value="inactive" @selected(old('status', $team->status?->value) === 'inactive')>Pasif</option></select></div>
        </div>

        @if($errors->any())<div class="alert alert-danger mt-3">{{ $errors->first() }}</div>@endif
        <div class="mt-4"><button class="btn btn-primary">Kaydet</button><a class="btn btn-light" href="{{ route('admin.teams.index') }}">Vazgeç</a></div>
    </form>
</div>
@endsection
