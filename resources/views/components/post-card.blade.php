@props(['post', 'showTeam' => true])
<article {{ $attributes->class(['post-card']) }}>
    @if($showTeam)
        <a class="post-team" href="{{ route('teams.show', $post->team) }}">
            <x-team-logo :team="$post->team" size="sm" />
            <span>{{ $post->team->name }}</span>
        </a>
    @endif

    <a href="{{ route('posts.show', [$post->team, $post]) }}" aria-label="Gönderiyi ve yorumları aç">
        @if($post->image_path)
            <img class="post-media" src="{{ Storage::url($post->image_path) }}" alt="{{ $post->team->name }} gönderi görseli" loading="lazy">
        @else
            <div class="text-post"><span>{{ Str::limit($post->body, 240) }}</span></div>
        @endif
    </a>
    <div class="post-body">
        @if($post->image_path)<p class="post-copy">{{ $post->body }}</p>@endif
        <livewire:post-actions :post="$post" :key="'post-actions-'.$post->id" />
        <div class="post-date">{{ $post->published_at->diffForHumans() }}</div>
    </div>
</article>
