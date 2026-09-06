@props(['post'])
@php($mediaItems = $post->media)

<div
    class="post-media-frame post-media-carousel"
    x-data="postMediaCarousel({{ $mediaItems->count() }})"
    data-media-total="{{ $mediaItems->count() }}"
    @if($mediaItems->count() > 1) tabindex="0" role="group" aria-label="{{ $mediaItems->count() }} görselli gönderi" @keydown.left.prevent="goTo(active - 1)" @keydown.right.prevent="goTo(active + 1)" @endif
>
    <div class="post-media-track" x-ref="track" @scroll.passive="sync">
        @foreach($mediaItems as $index => $media)
            <a class="post-media-slide" href="{{ route('posts.show', [$post->team, $post]) }}" aria-label="Gönderiyi ve yorumları aç{{ $mediaItems->count() > 1 ? ' · Görsel '.($index + 1) : '' }}">
                <img class="post-media" src="{{ Storage::url($media->path) }}" alt="{{ $post->team->name }} gönderi görseli {{ $index + 1 }}" width="1080" height="1350" loading="lazy" decoding="async">
            </a>
        @endforeach
    </div>

    @if($mediaItems->count() > 1)
        <div class="post-media-counter" aria-live="polite"><span x-text="active + 1">1</span><span>/{{ $mediaItems->count() }}</span></div>
        <button class="post-media-control previous" type="button" @click="goTo(active - 1)" x-show="active > 0" x-cloak aria-label="Önceki görsel"><x-ui.icon name="chevron-left" /></button>
        <button class="post-media-control next" type="button" @click="goTo(active + 1)" x-show="active < total - 1" aria-label="Sonraki görsel"><x-ui.icon name="chevron-right" /></button>
    @endif
</div>
