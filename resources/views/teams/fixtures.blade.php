@extends('layouts.app')
@section('title', $team->name.' Fikstür')
@section('mobile-title', $team->name)
@section('mobile-back', route('teams.index'))
@section('content')
<div class="feed-column public-feed mx-auto">
    @include('teams.partials.profile-header', ['activeTab' => 'fixtures'])

    <div class="team-tab-content">
        @if(! $hasFootballTeam)
            <div class="empty-state team-tab-empty"><x-ui.icon name="calendar" /><strong>Fikstür eşleşmesi bulunamadı</strong><p>Bu takım için fikstür verisi henüz eşleştirilmedi.</p></div>
        @else
            <nav class="competition-chips" aria-label="Organizasyon filtresi">
                <a href="{{ route('teams.fixtures', ['team' => $team, 'view' => $view]) }}" @class(['active' => $selectedCompetitionId === null])>Tümü</a>
                @foreach($competitions as $competition)
                    <a href="{{ route('teams.fixtures', ['team' => $team, 'competition' => $competition->id, 'view' => $view]) }}" @class(['active' => $selectedCompetitionId === $competition->id])>{{ $competition->display_name ?: $competition->name }}</a>
                @endforeach
            </nav>

            <nav class="fixture-segments" aria-label="Fikstür görünümü">
                <a href="{{ route('teams.fixtures', array_filter(['team' => $team, 'competition' => $selectedCompetitionId, 'view' => 'upcoming'])) }}" @class(['active' => $view === 'upcoming']) @if($view === 'upcoming') aria-current="page" @endif>Yaklaşan</a>
                <a href="{{ route('teams.fixtures', array_filter(['team' => $team, 'competition' => $selectedCompetitionId, 'view' => 'results'])) }}" @class(['active' => $view === 'results']) @if($view === 'results') aria-current="page" @endif>Sonuçlar</a>
            </nav>

            @if($view === 'upcoming')
                @if($nextMatch)
                    <x-football-match-card :match="$nextMatch" variant="featured" />
                @endif

                <section class="team-match-list" aria-labelledby="upcoming-matches-title">
                    <div class="team-section-heading"><h2 id="upcoming-matches-title">Yaklaşan Maçlar</h2><span>İlk {{ $matches->count() }} maç</span></div>
                    @forelse($matches as $match)
                        <x-football-match-card :match="$match" variant="compact" />
                    @empty
                        <div class="empty-state team-tab-empty"><x-ui.icon name="calendar" /><strong>Yaklaşan maç yok</strong><p>Bu filtrede planlanmış bir maç bulunmuyor.</p></div>
                    @endforelse
                </section>
            @else
                <section class="team-match-list" aria-labelledby="results-title">
                    <div class="team-section-heading"><h2 id="results-title">Sonuçlar</h2><span>Yeni → eski</span></div>
                    @forelse($matches as $match)
                        <x-football-match-card :match="$match" variant="compact" />
                    @empty
                        <div class="empty-state team-tab-empty"><x-ui.icon name="football" /><strong>Sonuç bulunamadı</strong><p>Bu filtrede tamamlanmış bir maç bulunmuyor.</p></div>
                    @endforelse
                </section>
            @endif
        @endif
    </div>
</div>
@endsection
