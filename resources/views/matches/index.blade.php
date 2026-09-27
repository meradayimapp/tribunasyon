@extends('layouts.app')
@section('title', 'Maçlar')
@section('mobile-title', 'Maçlar')

@php
    $baseQuery = fn (array $changes = []) => array_filter(array_merge([
        'date' => $date->toDateString(),
        'status' => $status !== 'all' ? $status : null,
        'competition' => $competitionSlug !== 'all' ? $competitionSlug : null,
    ], $changes), fn ($value) => $value !== null);
    $statusLabels = [
        'all' => 'Tümü',
        'live' => 'Canlı',
        'upcoming' => 'Yaklaşan',
        'finished' => 'Tamamlanan',
    ];
@endphp

@section('content')
<div
    class="feed-column matches-center-page mx-auto"
    x-data="competitionLiveState(@js(route('matches.index.state')), @js($initialStates))"
    @visibilitychange.document="visibilityChanged()"
>
    <header class="matches-center-header">
        <h1>Maçlar</h1>
        <p>{{ $date->locale('tr')->translatedFormat('d F l') }}</p>
    </header>

    <nav class="matches-status-filters" aria-label="Maç durumu filtreleri">
        @foreach($statusLabels as $key => $label)
            <a
                href="{{ route('matches.index', $baseQuery(['status' => $key === 'all' ? null : $key])) }}"
                @class(['active' => $status === $key, 'is-live' => $key === 'live'])
                @if($status === $key) aria-current="page" @endif
            >@if($key === 'live')<i aria-hidden="true"></i>@endif{{ $label }}</a>
        @endforeach
    </nav>

    <x-football.date-selector :date="$date" :today="$today" :status="$status" :competition-slug="$competitionSlug" />

    <form class="matches-league-filter" method="GET" action="{{ route('matches.index') }}">
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        @if($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
        <label for="matches-competition-filter">
            <x-ui.icon name="filter" />
            <span>Ligler / Filtre</span>
        </label>
        <select id="matches-competition-filter" name="competition" onchange="this.form.submit()">
            <option value="all" @selected($competitionSlug === 'all')>Tüm turnuvalar</option>
            @foreach($competitions as $competition)
                <option value="{{ $competition->slug }}" @selected($competitionSlug === $competition->slug)>
                    {{ $competition->display_name ?: $competition->name }}
                </option>
            @endforeach
        </select>
        <button type="submit">Uygula</button>
    </form>

    <div class="matches-groups">
        @forelse($matchGroups as $groupMatches)
            <x-football.competition-match-group :matches="$groupMatches" />
        @empty
            <section class="matches-empty-state" aria-live="polite">
                <x-ui.icon name="calendar" />
                <p><strong>{{ $date->locale('tr')->translatedFormat('d F') }}</strong>'de seçili filtrelerde maç bulunmuyor.</p>
                <div>
                    <a href="{{ route('matches.index', $baseQuery(['date' => $date->subDay()->toDateString()])) }}"><x-ui.icon name="chevron-left" /> Önceki gün</a>
                    <a href="{{ route('matches.index', $baseQuery(['date' => $date->addDay()->toDateString()])) }}">Sonraki gün <x-ui.icon name="chevron-right" /></a>
                </div>
            </section>
        @endforelse
    </div>
</div>
@endsection
