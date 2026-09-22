@extends('layouts.app')
@section('title', 'Maçlar')
@section('mobile-title', 'Maçlar')
@section('content')
<div class="feed-column matches-today-page mx-auto">
    <div class="page-head">
        <div class="eyebrow">Maç merkezi</div>
        <h1 class="page-title mb-1">Bugünün maçları</h1>
        <p class="muted mb-3">{{ $date->locale('tr')->translatedFormat('d F Y, l') }}</p>
    </div>

    <nav class="matches-competition-tabs" aria-label="Organizasyonlar">
        @foreach($competitions as $competition)
            <a
                href="{{ route('matches.index', ['competition' => $competition->provider_league_id]) }}"
                @class(['active' => $selectedCompetition?->is($competition)])
                @if($selectedCompetition?->is($competition)) aria-current="page" @endif
            >{{ $competition->display_name ?: $competition->name }}</a>
        @endforeach
    </nav>

    @if($selectedCompetition?->provider_league_id === \App\Models\FootballCompetition::NATIONS_LEAGUE_PROVIDER_ID)
        <a class="competition-center-link" href="{{ route('competitions.show', ['competition' => 'uluslar-ligi']) }}">
            <span><x-ui.icon name="trophy" /><span><strong>Uluslar Ligi Merkezi</strong><small>Tüm fikstür ve puan durumu</small></span></span>
            <x-ui.icon name="chevron-right" />
        </a>
    @endif

    @if($selectedCompetition === null)
        <div class="empty-state mx-3 mx-md-0">
            <x-ui.icon name="calendar" />
            <strong>Takip edilen organizasyon bulunamadı.</strong>
        </div>
    @elseif($liveMatches->isEmpty() && $upcomingMatches->isEmpty() && $finishedMatches->isEmpty())
        <div class="empty-state mx-3 mx-md-0">
            <x-ui.icon name="calendar" />
            <strong>Bugün bu organizasyonda maç bulunmuyor.</strong>
            <p>Başka bir organizasyon seçerek günün programına bakabilirsin.</p>
        </div>
    @else
        @if($liveMatches->isNotEmpty())
            <section class="today-match-group" aria-labelledby="today-live-title">
                <h2 id="today-live-title" class="today-match-group-title is-live"><i aria-hidden="true"></i> Canlı</h2>
                @foreach($liveMatches as $match)
                    <x-football-match-card :match="$match" />
                @endforeach
            </section>
        @endif

        @if($upcomingMatches->isNotEmpty())
            <section class="today-match-group" aria-labelledby="today-upcoming-title">
                <h2 id="today-upcoming-title" class="today-match-group-title">Bugün</h2>
                @foreach($upcomingMatches as $match)
                    <x-football-match-card :match="$match" />
                @endforeach
            </section>
        @endif

        @if($finishedMatches->isNotEmpty())
            <section class="today-match-group" aria-labelledby="today-finished-title">
                <h2 id="today-finished-title" class="today-match-group-title">Bitti</h2>
                @foreach($finishedMatches as $match)
                    <x-football-match-card :match="$match" />
                @endforeach
            </section>
        @endif
    @endif
</div>
@endsection
