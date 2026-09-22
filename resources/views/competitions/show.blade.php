@extends('layouts.app')
@section('title', ($competition->display_name ?: $competition->name).' '.($displaySeason ?: ''))
@section('mobile-title', $competition->display_name ?: $competition->name)
@section('mobile-back', route('matches.index', ['competition' => $competition->provider_league_id]))

@php
    $competitionName = $competition->provider_league_id === \App\Models\FootballCompetition::NATIONS_LEAGUE_PROVIDER_ID ? 'UEFA Uluslar Ligi' : ($competition->display_name ?: $competition->name);
    $initialStates = $pollingMatches->mapWithKeys(fn ($match) => [$match->id => [
        'score' => ['home' => $match->home_score, 'away' => $match->away_score],
        'is_live' => $match->is_live,
        'is_finished' => $match->isFinished(),
        'minute' => $match->displayMinute(),
        'status_display' => $match->statusLabel(),
        'polling_active' => $match->shouldPollLiveState(),
    ]]);
    $fixtureGroups = collect($groups)->map(fn ($items) => $items->groupBy(fn ($match) => $match->kickoffInDisplayTimezone()->toDateString()));
@endphp

@section('content')
<div class="competition-page mx-auto" x-data="competitionLiveState(@js(route('competitions.state', $competition->slug)), @js($initialStates))" @visibilitychange.document="visibilityChanged()">
    <header class="competition-hero surface mobile-edge">
        <div class="competition-identity">
            @if($logo = $competition->logoUrl())<img src="{{ $logo }}" alt="{{ $competitionName }} logosu">@else<span class="competition-logo-fallback"><x-ui.icon name="trophy" /></span>@endif
            <div><p>Turnuva merkezi</p><h1>{{ $competitionName }}</h1>@if($displaySeason)<strong>{{ $displaySeason }}</strong>@endif</div>
        </div>
        <nav class="competition-tabs" aria-label="Turnuva bölümleri">
            @foreach(['genel-bakis' => 'Genel Bakış', 'fikstur' => 'Fikstür', 'puan-durumu' => 'Puan Durumu', 'takimlar' => 'Takımlar'] as $key => $label)
                <a href="{{ route('competitions.show', ['competition' => $competition->slug, 'tab' => $key === 'genel-bakis' ? null : $key]) }}" @class(['active' => $tab === $key]) @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </header>

    @if($tab === 'genel-bakis')
        <div class="competition-overview">
            @if($liveMatches->isNotEmpty())
                @include('competitions.partials.match-section', ['sectionId' => 'live-matches', 'title' => 'Canlı Maçlar', 'sectionMatches' => $liveMatches])
            @endif
            @include('competitions.partials.match-section', ['sectionId' => 'today-matches', 'title' => 'Bugünün Maçları', 'sectionMatches' => $todayMatches, 'empty' => 'Bugün bu turnuvada maç yok.'])

            @if($turkeyProviderTeamId)
                @php
                    $turkeyRecent = $turkeyMatches->filter->isCompleted()->sortByDesc('kickoff_at')->take(3)->values();
                    $turkeyUpcoming = $turkeyMatches->reject->isFinished()->filter(fn ($match) => $match->kickoff_at->isFuture())->take(3)->values();
                    $turkeyFeatured = $turkeyRecent->merge($turkeyUpcoming)->sortBy('kickoff_at')->values();
                @endphp
                @include('competitions.partials.match-section', ['sectionId' => 'turkey-matches', 'title' => 'Türkiye’nin Maçları', 'sectionMatches' => $turkeyFeatured])
            @endif

            <div class="competition-overview-grid">
                @include('competitions.partials.match-section', ['sectionId' => 'recent-matches', 'title' => 'Son Sonuçlar', 'sectionMatches' => $recentMatches, 'actionUrl' => route('competitions.show', ['competition' => $competition->slug, 'tab' => 'fikstur']), 'actionLabel' => 'Tüm fikstür'])
                @include('competitions.partials.match-section', ['sectionId' => 'upcoming-matches', 'title' => 'Sıradaki Maçlar', 'sectionMatches' => $upcomingMatches, 'actionUrl' => route('competitions.show', ['competition' => $competition->slug, 'tab' => 'fikstur']), 'actionLabel' => 'Tüm fikstür'])
            </div>

            <section class="competition-section standings-content">
                <div class="competition-section-heading"><h2>Puan Durumu Özeti</h2><a href="{{ route('competitions.show', ['competition' => $competition->slug, 'tab' => 'puan-durumu']) }}">Tüm puan durumunu gör <x-ui.icon name="chevron-right" /></a></div>
                @php
                    $turkeyTable = $turkeyProviderTeamId ? $tables->first(fn ($table) => collect($table['rows'])->contains('provider_team_id', $turkeyProviderTeamId)) : null;
                    $shownTables = collect($turkeyTable ? [$turkeyTable] : $tables->take(2))->map(fn ($table) => array_merge($table, ['rows' => array_slice($table['rows'], 0, 6)]));
                @endphp
                @include('competitions.partials.standings')
            </section>
        </div>
    @elseif($tab === 'fikstur')
        <section class="competition-section competition-fixtures">
            <div class="competition-section-heading"><div><h2>Sezon Fikstürü</h2><p>{{ $matches->count() }} maç</p></div></div>
            @forelse(['past' => 'Geçmiş', 'today' => 'Bugün', 'future' => 'Gelecek'] as $groupKey => $groupTitle)
                @if($fixtureGroups[$groupKey]->isNotEmpty())
                    <div class="fixture-period"><h3>{{ $groupTitle }}</h3>
                        @foreach($fixtureGroups[$groupKey] as $date => $dateMatches)
                            <div class="fixture-date-group">
                                <h4>{{ \Carbon\CarbonImmutable::parse($date)->locale('tr')->translatedFormat('d F l') }}</h4>
                                <div class="competition-match-list">@foreach($dateMatches as $match)@include('competitions.partials.match-row', ['match' => $match])@endforeach</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @empty
                <p class="competition-empty">Bu turnuva için fikstür bulunamadı.</p>
            @endforelse
        </section>
    @elseif($tab === 'puan-durumu')
        <section class="competition-section standings-content">
            <div class="competition-section-heading"><div><h2>Puan Durumu</h2>@if($standingsSeason)<p>{{ $standingsSeason }}</p>@endif</div></div>
            @php($shownTables = $tables)
            @include('competitions.partials.standings')
        </section>
    @else
        <section class="competition-section">
            <div class="competition-section-heading"><div><h2>Takımlar</h2><p>{{ $teams->count() }} takım</p></div></div>
            <div class="competition-teams-grid">
                @forelse($teams as $team)
                    <a class="competition-team-card" href="{{ $team->publicUrl() }}">
                        @if($logo = $team->logoUrl())<img src="{{ $logo }}" alt="" loading="lazy" decoding="async">@else<span>{{ mb_strtoupper(mb_substr($team->resolved_name, 0, 2)) }}</span>@endif
                        <div><strong>{{ $team->resolved_name }}</strong>@if($team->team?->short_name)<small>{{ $team->team->short_name }}</small>@elseif($team->country)<small>{{ $team->country }}</small>@endif</div>
                        <x-ui.icon name="chevron-right" />
                    </a>
                @empty<p class="competition-empty">Takım bilgisi bulunamadı.</p>@endforelse
            </div>
        </section>
    @endif
</div>
@endsection
