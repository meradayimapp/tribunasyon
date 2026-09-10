@extends('layouts.app')
@section('title', $post->exists ? 'Gönderiyi düzenle' : 'Hızlı gönderi')
@section('content')
@php
    $existingMedia = $post->exists
        ? $post->media->map(fn ($media) => ['id' => $media->id, 'url' => Storage::url($media->path)])->values()
        : collect();
@endphp
<div class="panel-shell">
    <div class="eyebrow">Moderatör paneli</div>
    <h1 class="page-title">{{ $post->exists ? 'Gönderiyi düzenle' : 'Hızlı gönderi oluştur' }}</h1>
    <x-panel-nav type="moderator" />

    <form class="surface" method="POST" enctype="multipart/form-data" action="{{ $post->exists ? route('moderator.posts.update', $post) : route('moderator.posts.store') }}" x-data="postMediaManager(@js($existingMedia), 10)">
        @csrf
        @if($post->exists)@method('PUT')@endif
        <input type="hidden" name="media_editor_present" value="0" x-init="$el.value = '1'">

        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Takım</label><select class="form-select" name="team_id" required>@foreach($teams as $team)<option value="{{ $team->id }}" @selected(old('team_id', $post->team_id) == $team->id)>{{ $team->name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Yayın durumu</label><select class="form-select" name="status"><option value="published" @selected(old('status', $post->status?->value ?? 'published') === 'published')>Hemen yayınla</option><option value="draft" @selected(old('status', $post->status?->value) === 'draft')>Taslak</option><option value="archived" @selected(old('status', $post->status?->value) === 'archived')>Arşivle</option></select></div>
            <div class="col-12"><label class="form-label">Gönderi metni</label><textarea class="form-control @error('body') is-invalid @enderror" name="body" rows="6" maxlength="5000" autofocus placeholder="Topluluğunuzla ne paylaşmak istiyorsunuz?">{{ old('body', $post->body) }}</textarea>@error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="seo-title">SEO başlığı <span class="muted fw-normal">(opsiyonel)</span></label><input id="seo-title" class="form-control @error('seo_title') is-invalid @enderror" name="seo_title" value="{{ old('seo_title', $post->seo_title) }}" maxlength="70" placeholder="Boşsa gönderiden otomatik üretilir">@error('seo_title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="seo-description">SEO açıklaması <span class="muted fw-normal">(opsiyonel)</span></label><textarea id="seo-description" class="form-control @error('seo_description') is-invalid @enderror" name="seo_description" rows="2" maxlength="160" placeholder="Boşsa gönderiden otomatik üretilir">{{ old('seo_description', $post->seo_description) }}</textarea>@error('seo_description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>

            <div class="col-12">
                <label class="form-label" for="post-images">Görseller <span class="muted fw-normal">(en fazla 10 · her biri JPEG, PNG veya WebP · 8 MB)</span></label>
                <input id="post-images" x-ref="mediaInput" type="file" class="form-control @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror" name="images[]" accept="image/jpeg,image/png,image/webp" multiple @change="addFiles($event)">
                @error('images')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('images.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('media_order')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="media-editor-message" x-show="message" x-text="message" x-cloak></div>

                <div class="media-editor-grid" x-show="items.length" x-cloak>
                    <template x-for="(item, index) in items" :key="item.key">
                        <div class="media-editor-item" draggable="true" @dragstart="startDrag(index)" @dragover.prevent @drop.prevent="dropAt(index)" @dragend="dragging = null">
                            <img :src="item.url" alt="Seçilen gönderi görseli önizlemesi">
                            <span class="media-editor-order" x-text="index + 1"></span>
                            <div class="media-editor-controls">
                                <button type="button" @click="move(index, index - 1)" :disabled="index === 0" aria-label="Görseli sola taşı"><x-ui.icon name="chevron-left" /></button>
                                <button type="button" @click="move(index, index + 1)" :disabled="index === items.length - 1" aria-label="Görseli sağa taşı"><x-ui.icon name="chevron-right" /></button>
                                <button type="button" class="remove" @click="remove(index)" aria-label="Görseli kaldır"><x-ui.icon name="close" /></button>
                            </div>
                            <input type="hidden" name="media_order[]" :value="token(item)">
                        </div>
                    </template>
                </div>
                <p class="media-editor-help" x-show="items.length > 1" x-cloak>Görselleri sürükleyin veya oklarla sıralayın. İlk görsel gönderinin kapak görselidir.</p>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">{{ $post->exists ? 'Değişiklikleri kaydet' : 'Gönderiyi kaydet' }}</button><a class="btn btn-light" href="{{ route('moderator.posts.index') }}">Vazgeç</a></div>
    </form>
</div>
@endsection
