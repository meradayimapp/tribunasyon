<div class="header-search-wrap">
    <form class="header-search" wire:submit="submit" role="search">
        <x-ui.icon name="search" />
        <input
            type="search"
            wire:model.live.debounce.300ms="query"
            placeholder="Takım veya gönderi ara..."
            aria-label="Takım veya gönderi ara"
            autocomplete="off"
            maxlength="100"
        >
        <span class="mini-loader" wire:loading wire:target="query"></span>
    </form>

    @error('query')<div class="header-search-error">{{ $message }}</div>@enderror

    @if($showSuggestions)
        <div class="search-suggestions">
            @if($teams->isNotEmpty())
                <div class="search-group-label">Takımlar</div>
                @foreach($teams as $team)
                    <a class="search-team-result" href="{{ route('teams.show', $team) }}">
                        <x-team-logo :team="$team" size="xs" />
                        <strong>{{ $team->name }}</strong>
                        <x-ui.icon name="chevron-right" />
                    </a>
                @endforeach
            @endif

            @if($posts->isNotEmpty())
                <div class="search-group-label">Gönderiler</div>
                @foreach($posts as $post)
                    <a class="search-post-result" href="{{ route('posts.show', [$post->team, $post]) }}">
                        <span>{{ Str::limit($post->body, 82) }}</span>
                        <small>{{ $post->team->name }} · {{ $post->published_at->diffForHumans() }}</small>
                    </a>
                @endforeach
            @endif

            @if($teams->isEmpty() && $posts->isEmpty())
                <div class="search-empty">Sonuç bulunamadı</div>
            @endif

            <a class="search-all" href="{{ route('search.index', ['q' => trim($query)]) }}">Tüm sonuçları gör <x-ui.icon name="arrow-right" /></a>
        </div>
    @endif
</div>
