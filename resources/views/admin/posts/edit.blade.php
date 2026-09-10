@extends('layouts.app')
@section('title', 'Gönderi SEO · Admin')
@section('content')
<div class="panel-shell">
    <div class="eyebrow">Yönetim</div>
    <h1 class="page-title">Gönderi SEO alanları</h1>
    <x-panel-nav />

    <form class="surface" method="POST" action="{{ route('admin.posts.update', $post) }}">
        @csrf
        @method('PUT')
        <p class="muted mb-4"><strong>{{ $post->team->name }}</strong> · {{ Str::limit($post->body, 120) }}</p>
        <div class="row g-3">
            <div class="col-12"><label class="form-label" for="seo-title">SEO başlığı <span class="muted fw-normal">(opsiyonel)</span></label><input id="seo-title" class="form-control @error('seo_title') is-invalid @enderror" name="seo_title" value="{{ old('seo_title', $post->seo_title) }}" maxlength="70" placeholder="Boşsa gönderiden otomatik üretilir">@error('seo_title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><label class="form-label" for="seo-description">SEO açıklaması <span class="muted fw-normal">(opsiyonel)</span></label><textarea id="seo-description" class="form-control @error('seo_description') is-invalid @enderror" name="seo_description" rows="3" maxlength="160" placeholder="Boşsa gönderiden otomatik üretilir">{{ old('seo_description', $post->seo_description) }}</textarea>@error('seo_description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        </div>
        <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">SEO alanlarını kaydet</button><a class="btn btn-light" href="{{ route('admin.posts.index') }}">Vazgeç</a></div>
    </form>
</div>
@endsection
