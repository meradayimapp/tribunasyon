@props(['post', 'showTeam' => true])
@php
    $isLong = Str::length($post->body) > 220;
    $preview = Str::limit($post->body, 220);
@endphp

<article {{ $attributes->class(['post-card']) }} x-data="{ expanded: false, comments: {{ (int) $post->comments_count }} }" x-on:comment-created.window="comments++">
    @if($showTeam)
        <header class="post-header">
            <a class="post-team" href="{{ route('teams.show', $post->team) }}">
                <x-team-logo :team="$post->team" size="sm" />
                <span class="post-team-copy"><strong>{{ $post->team->name }}</strong><small>{{ $post->published_at->diffForHumans() }}</small></span>
            </a>
        </header>
    @endif

    @if($post->image_path)
        <a class="post-media-frame" href="{{ route('posts.show', [$post->team, $post]) }}" aria-label="Gönderiyi ve yorumları aç">
            <img class="post-media" src="{{ Storage::url($post->image_path) }}" alt="{{ $post->team->name }} gönderi görseli" loading="lazy" decoding="async">
        </a>
    @else
        <div class="text-post">
            <p>
                <span x-show="!expanded">{{ $preview }}</span>
                @if($isLong)<span x-show="expanded" x-cloak>{{ $post->body }}</span>@endif
            </p>
        </div>
    @endif

    <div class="post-body">
        <livewire:post-actions :post="$post" :key="'post-actions-'.$post->id" />

        @if($post->image_path)
            <p class="post-copy">
                <span x-show="!expanded">{{ $preview }}</span>
                @if($isLong)<span x-show="expanded" x-cloak>{{ $post->body }}</span>@endif
                @if($isLong)<button class="more-button" type="button" @click="expanded = !expanded" x-text="expanded ? 'daha az göster' : 'devamını gör'"></button>@endif
            </p>
        @elseif($isLong)
            <button class="more-button text-post-more" type="button" @click="expanded = !expanded" x-text="expanded ? 'daha az göster' : 'devamını gör'"></button>
        @endif

        <a class="comments-link" href="{{ route('posts.show', [$post->team, $post]) }}#yorumlar" x-text="comments > 0 ? comments.toLocaleString('tr-TR') + ' yorumun tümünü gör' : 'İlk yorumu yaz'">{{ $post->comments_count > 0 ? number_format($post->comments_count, 0, ',', '.').' yorumun tümünü gör' : 'İlk yorumu yaz' }}</a>
        @unless($showTeam)<div class="post-date">{{ $post->published_at->diffForHumans() }}</div>@endunless
    </div>
</article>
