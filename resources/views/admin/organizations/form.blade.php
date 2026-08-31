@extends('layouts.app')
@section('title', $organization->exists ? 'Organizasyonu düzenle' : 'Organizasyon oluştur')
@section('content')
<div class="panel-shell">
    <div class="eyebrow">Yönetim</div>
    <h1 class="page-title">{{ $organization->exists ? 'Organizasyonu düzenle' : 'Yeni organizasyon' }}</h1>
    <x-panel-nav />

    <form class="surface" method="POST" enctype="multipart/form-data" action="{{ $organization->exists ? route('admin.organizations.update', $organization) : route('admin.organizations.store') }}">
        @csrf
        @if($organization->exists)@method('PUT')@endif

        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Organizasyon adı</label><input class="form-control" name="name" value="{{ old('name', $organization->name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Slug</label><input class="form-control" name="slug" value="{{ old('slug', $organization->slug) }}" placeholder="Otomatik oluşturulur"></div>
            <div class="col-md-8">
                <x-admin.image-upload
                    name="logo_file"
                    label="Organizasyon logosu"
                    :current-url="$organization->logoUrl()"
                    :required="! $organization->exists"
                    remove-name="remove_logo"
                />
            </div>
            <div class="col-md-4">
                <label class="form-label">Durum</label>
                <select class="form-select" name="status">
                    @foreach(\App\Enums\OrganizationStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $organization->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($errors->any())<div class="alert alert-danger mt-3">{{ $errors->first() }}</div>@endif
        <div class="mt-4"><button class="btn btn-primary">Kaydet</button><a class="btn btn-light" href="{{ route('admin.organizations.index') }}">Vazgeç</a></div>
    </form>
</div>
@endsection
