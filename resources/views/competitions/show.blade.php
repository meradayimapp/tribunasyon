@extends('layouts.app')
@section('title', ($competition->display_name ?: $competition->name).' '.($displaySeason ?: ''))
@section('mobile-title', $competition->display_name ?: $competition->name)
@section('mobile-back', route('matches.index', ['competition' => $competition->provider_league_id]))

@php
    $competitionName = $competition->provider_league_id === \App\Models\FootballCompetition::NATIONS_LEAGUE_PROVIDER_ID ? 'UEFA Uluslar Ligi' : ($competition->display_name ?: $competition->name);
    $seasonLabel = preg_match('/^(\d{4})\/\d{2}(\d{2})$/', (string) $displaySeason, $seasonParts) ? $seasonParts[1].'/'.$seasonParts[2] : $displaySeason;
    $initialStates = $pollingMatches->mapWithKeys(fn ($match) => [$match->id => [
        'status' => $match->status,
        'score' => ['home' => $match->home_score, 'away' => $match->away_score],
        'is_live' => $match->is_live,
        'is_half_time' => $match->isHalfTime(),
        'is_finished' => $match->isFinished(),
        'minute' => $match->displayMinute(),
        'status_display' => $match->statusLabel(),
        'polling_active' => $match->shouldPollLiveState(),
    ]]);
@endphp

@section('content')
<div class="competition-page mx-auto" x-data="competitionLiveState(@js(route('competitions.state', $competition->slug)), @js($initialStates))" @visibilitychange.document="visibilityChanged()">
    <header class="competition-hero surface mobile-edge">
        <div class="competition-identity">
            @if($logo = $competition->logoUrl())
                <img src="{{ $logo }}" alt="{{ $competitionName }} logosu">
            @else
                <span class="competition-logo-fallback"><x-ui.icon name="trophy" /></span>
            @endif
            <div><h1>{{ $competitionName }}</h1>@if($seasonLabel)<strong>{{ $seasonLabel }}</strong>@endif</div>
        </div>
        <nav class="competition-tabs" aria-label="Turnuva bölümleri">
            @foreach(['genel-bakis' => 'Genel Bakış', 'fikstur' => 'Fikstür', 'puan-durumu' => 'Puan Durumu', 'takimlar' => 'Takımlar'] as $key => $label)
                <a href="{{ route('competitions.show', ['competition' => $competition->slug, 'tab' => $key === 'genel-bakis' ? null : $key]) }}" @class(['active' => $tab === $key]) @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </header>

    @if($tab === 'genel-bakis')
        <section class="competition-schedule" aria-labelledby="competition-schedule-title">
            <div class="competition-page-heading">
                <div><span>Turnuva merkezi</span><h2 id="competition-schedule-title">Maç Programı</h2></div>
                <a href="{{ route('competitions.show', ['competition' => $competition->slug, 'tab' => 'fikstur']) }}">Tüm maçlar <x-ui.icon name="chevron-right" /></a>
            </div>

            @if(! $overviewHasTodayMatches && $overviewMatches->isNotEmpty())
                <p class="competition-inline-note">Bugün maç bulunmuyor.</p>
                <h3 class="competition-upcoming-label">Yaklaşan maçlar</h3>
            @endif

            <div class="competition-date-list">
                @forelse($overviewDateGroups as $date => $dateMatches)
                    <x-football.competition-date-group :date="$date" :matches="$dateMatches" :turkey-provider-team-id="$turkeyProviderTeamId" />
                @empty
                    <p class="competition-inline-note">Bu turnuva için maç programı bulunamadı.</p>
                @endforelse
            </div>

            @if($matches->count() > $overviewMatches->count())
                <a class="competition-all-fixtures" href="{{ route('competitions.show', ['competition' => $competition->slug, 'tab' => 'fikstur']) }}">Tüm fikstürü görüntüle <x-ui.icon name="chevron-right" /></a>
            @endif
        </section>
    @elseif($tab === 'fikstur')
        <section class="competition-schedule competition-fixtures" aria-labelledby="competition-fixtures-title">
            <div class="competition-page-heading">
                <div><span>{{ $matches->count() }} maç</span><h2 id="competition-fixtures-title">Fikstür</h2></div>
            </div>
            <nav class="competition-fixture-filters" aria-label="Fikstür filtreleri">
                @foreach(['tumu' => 'Tümü', 'yaklasan' => 'Yaklaşan', 'tamamlanan' => 'Tamamlanan'] as $key => $label)
                    <a href="{{ route('competitions.show', ['competition' => $competition->slug, 'tab' => 'fikstur', 'filter' => $key === 'tumu' ? null : $key]) }}" @class(['active' => $fixtureFilter === $key]) @if($fixtureFilter === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="competition-date-list">
                @forelse($fixtureDateGroups as $date => $dateMatches)
                    <x-football.competition-date-group :date="$date" :matches="$dateMatches" :turkey-provider-team-id="$turkeyProviderTeamId" />
                @empty
                    <p class="competition-inline-note">Bu filtrede maç bulunmuyor.</p>
                @endforelse
            </div>
        </section>
    @elseif($tab === 'puan-durumu')
        <section class="competition-standings" aria-labelledby="competition-standings-title">
            <div class="competition-page-heading">
                <div>@if($standingsSeason)<span>{{ $standingsSeason }}</span>@endif<h2 id="competition-standings-title">Puan Durumu</h2></div>
            </div>
            @php($shownTables = $tables)
            @include('competitions.partials.standings')
        </section>
    @else
        <section class="competition-teams" aria-labelledby="competition-teams-title">
            <div class="competition-page-heading">
                <div><span>{{ $teams->count() }} takım</span><h2 id="competition-teams-title">Takımlar</h2></div>
            </div>
            <div class="competition-teams-grid">
                @forelse($teams as $team)
                    <a class="competition-team-card" href="{{ $team->publicUrl() }}">
                        @if($logo = $team->logoUrl())<img src="{{ $logo }}" alt="" loading="lazy" decoding="async">@else<span>{{ mb_strtoupper(mb_substr($team->resolved_name, 0, 2)) }}</span>@endif
                        <strong>{{ $team->resolved_name }}</strong>
                        <x-ui.icon name="chevron-right" />
                    </a>
                @empty
                    <p class="competition-inline-note">Takım bilgisi bulunamadı.</p>
                @endforelse
            </div>
        </section>
    @endif
</div>
@endsection
