@extends('layouts.app')
@section('title', 'Arama')
@section('mobile-title', 'Ara')
@section('mobile-back', route('home'))
@section('content')
<div class="search-page mx-auto">
    <div class="page-head mobile-pad">
        <div class="eyebrow">Topluluk araması</div>
        <h1 class="page-title">Takım ve gönderileri keşfet</h1>
    </div>

    <form class="search-page-form" method="GET" action="{{ route('search.index') }}" role="search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" name="q" value="{{ $query }}" placeholder="Takım veya gönderi ara..." aria-label="Takım veya gönderi ara" maxlength="100" autofocus>
        <button type="submit">Ara</button>
    </form>
    @error('q')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

    @if($query !== '')
        <section class="search-section">
            <div class="section-heading"><h2>Takımlar</h2><span>{{ $teams->count() }}</span></div>
            <div class="search-team-list">
                @forelse($teams as $team)
                    <a class="search-team-card" href="{{ route('teams.show', $team) }}">
                        <x-team-logo :team="$team" size="md" />
                        <span><strong>{{ $team->name }}</strong><small>{{ $team->short_name }}</small></span>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
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
                        @if($post->image_path)
                            <img src="{{ Storage::url($post->image_path) }}" alt="" width="64" height="80" loading="lazy">
                        @endif
                        <span><strong>{{ Str::limit($post->body, 150) }}</strong><small>{{ $post->team->name }} · {{ $post->published_at->diffForHumans() }}</small></span>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </a>
                @empty
                    <div class="search-section-empty">Eşleşen gönderi bulunamadı.</div>
                @endforelse
            </div>
        </section>

        @if($teams->isEmpty() && $posts->isEmpty())
            <div class="empty-state search-no-results"><i class="bi bi-search"></i><strong>Sonuç bulunamadı</strong><p>Başka bir takım adı veya ifade deneyin.</p></div>
        @endif
    @else
        <div class="search-prompt"><i class="bi bi-search"></i><span>Takım adı veya gönderi metni yazarak aramaya başlayın.</span></div>
    @endif
</div>
@endsection
