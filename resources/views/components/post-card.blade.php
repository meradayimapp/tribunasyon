@props(['post', 'showTeam' => true])
@php
    $isLong = Str::length($post->body) > 220;
    $preview = Str::limit($post->body, 220);
    $hasMedia = $post->media->isNotEmpty();
@endphp

<article {{ $attributes->class(['post-card']) }} x-data="{ expanded: false, comments: {{ (int) $post->comments_count }} }" x-on:comment-created.window="comments++">
    @if($showTeam)
        <header class="post-header">
            <a class="post-team" href="{{ route('teams.show', $post->team) }}">
                <x-team-logo :team="$post->team" size="sm" />
                <span class="post-team-copy"><span class="post-team-name-line"><strong>{{ $post->team->name }}</strong><x-organization-badge :team="$post->team" /></span><small>{{ $post->published_at->diffForHumans() }}</small></span>
            </a>
        </header>
    @endif

    @if($hasMedia)
        <x-post-media-carousel :post="$post" />
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

        @if($hasMedia)
            <p class="post-copy"><span class="post-copy-text" x-show="!expanded">{{ $preview }}</span>
                @if($isLong)<span class="post-copy-text" x-show="expanded" x-cloak>{{ $post->body }}</span>@endif
                @if($isLong)<button class="more-button" type="button" @click="expanded = !expanded" x-text="expanded ? 'daha az göster' : 'devamını gör'"></button>@endif
            </p>
        @elseif($isLong)
            <button class="more-button text-post-more" type="button" @click="expanded = !expanded" x-text="expanded ? 'daha az göster' : 'devamını gör'"></button>
        @endif

        <a class="comments-link" href="{{ route('posts.show', [$post->team, $post]) }}#yorumlar" x-text="comments > 0 ? comments.toLocaleString('tr-TR') + ' yorumun tümünü gör' : 'İlk yorumu yaz'">{{ $post->comments_count > 0 ? number_format($post->comments_count, 0, ',', '.').' yorumun tümünü gör' : 'İlk yorumu yaz' }}</a>
        @unless($showTeam)<div class="post-date">{{ $post->published_at->diffForHumans() }}</div>@endunless
    </div>
</article>
