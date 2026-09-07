@extends('layouts.app')
@section('title', 'Arama')
@section('mobile-title', 'Ara')
@section('mobile-back', route('home'))
@section('content')
<div class="search-page mx-auto">
    <div class="page-head mobile-pad">
        <div class="eyebrow">Topluluk araması</div>
        <h1 class="page-title">Oyuncu, takım ve gönderileri keşfet</h1>
    </div>

    <form class="search-page-form" method="GET" action="{{ route('search.index') }}" role="search">
        <x-ui.icon name="search" />
        <input type="search" name="q" value="{{ $query }}" placeholder="Oyuncu, takım veya gönderi ara..." aria-label="Oyuncu, takım veya gönderi ara" maxlength="100" autofocus>
        <button type="submit">Ara</button>
    </form>
    @error('q')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

    @if($query !== '')
        <section class="search-section">
            <div class="section-heading"><h2>Oyuncular</h2><span>{{ $players->count() }}</span></div>
            <div class="search-team-list">
                @forelse($players as $player)
                    <a class="search-team-card" href="{{ route('players.show', $player) }}">
                        <span class="search-player-avatar">@if($player->photoUrl())<img src="{{ $player->photoUrl() }}" alt="{{ $player->name }} fotoğrafı">@else{{ mb_strtoupper(mb_substr($player->name, 0, 2)) }}@endif</span>
                        <span class="search-team-card-copy"><strong>{{ $player->name }}</strong><small>{{ collect([$player->position, $player->currentTeam?->name])->filter()->join(' · ') }}</small></span>
                        <x-ui.icon name="chevron-right" />
                    </a>
                @empty
                    <div class="search-section-empty">Eşleşen oyuncu bulunamadı.</div>
                @endforelse
            </div>
        </section>

        <section class="search-section">
            <div class="section-heading"><h2>Takımlar</h2><span>{{ $teams->count() }}</span></div>
            <div class="search-team-list">
                @forelse($teams as $team)
                    <a class="search-team-card" href="{{ route('teams.show', $team) }}">
                        <x-team-logo :team="$team" size="search" />
                        <span class="search-team-card-copy"><strong>{{ $team->name }}</strong><small>{{ $team->short_name }}</small></span>
                        <x-ui.icon name="chevron-right" />
                    </a>
                @empty
                    <div class="search-section-empty">Eşleşen takım bulunamadı.</div>
                @endforelse
            </div>
        </section>

        <section class="search-section">
            <div class="section-heading"><h2>Gönderiler</h2><span>{{ $posts->count() }}</span></div>
            <div class="search-post-list">
                @forelse($posts as $post)
                    <a class="search-post-card" href="{{ route('posts.show', [$post->team, $post]) }}">
                        @if($post->coverMedia)
                            <img src="{{ Storage::url($post->coverMedia->path) }}" alt="" width="64" height="80" loading="lazy">
                        @endif
                        <span><strong>{{ Str::limit($post->body, 150) }}</strong><small>{{ $post->team->name }} · {{ $post->published_at->diffForHumans() }}</small></span>
                        <x-ui.icon name="chevron-right" />
                    </a>
                @empty
                    <div class="search-section-empty">Eşleşen gönderi bulunamadı.</div>
                @endforelse
            </div>
        </section>

        @if($players->isEmpty() && $teams->isEmpty() && $posts->isEmpty())
            <div class="empty-state search-no-results"><x-ui.icon name="search" /><strong>Sonuç bulunamadı</strong><p>Başka bir takım adı veya ifade deneyin.</p></div>
        @endif
    @else
        <div class="search-prompt"><x-ui.icon name="search" /><span>Takım adı veya gönderi metni yazarak aramaya başlayın.</span></div>
    @endif
</div>
@endsection
