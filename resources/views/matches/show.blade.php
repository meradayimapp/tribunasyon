@extends('layouts.app')
@section('title', $match->homeTeam->resolved_name.' - '.$match->awayTeam->resolved_name)
@section('mobile-title', 'Maç Detayı')
@section('mobile-back', route('matches.index'))
@section('content')
<div class="feed-column mx-auto">
    <section
        class="match-detail surface mobile-edge"
        x-data="matchLiveState(@js(route('matches.state', $match)), @js($match->statePayload()))"
        @visibilitychange.document="visibilityChanged()"
    >
        <div class="match-detail-competition">{{ $match->competition->display_name ?: $match->competition->name }}</div>
        <div class="match-detail-scoreboard">
            <x-football-team-link :team="$match->homeTeam" class="match-detail-team" />
            <div class="match-detail-score">
                <strong>
                    <span x-show="scoreKnown" @if($match->home_score === null && $match->away_score === null) x-cloak @endif><span x-text="homeScore">{{ $match->home_score ?? '–' }}</span> – <span x-text="awayScore">{{ $match->away_score ?? '–' }}</span></span>
                    <span x-show="! scoreKnown" @if($match->home_score !== null || $match->away_score !== null) x-cloak @endif>{{ $match->kickoffTime() }}</span>
                </strong>
                <span :class="isLive ? 'text-danger' : 'muted'" x-text="statusDisplay">{{ $match->stateStatusLabel() }}</span>
                <small class="text-danger" x-show="isLive && minute !== null" x-text="minute + '\u2032'" @if(! $match->is_live || $match->live_minute === null) x-cloak @endif>{{ $match->live_minute !== null ? $match->live_minute.'′' : '' }}</small>
            </div>
            <x-football-team-link :team="$match->awayTeam" :away="true" class="match-detail-team" />
        </div>

        <dl class="match-detail-facts">
            <div><dt>Tarih</dt><dd>{{ $match->kickoffInDisplayTimezone()->translatedFormat('d F Y, l') }}</dd></div>
            @if($match->home_score !== null || $match->away_score !== null)<div><dt>Saat</dt><dd>{{ $match->kickoffTime() }}</dd></div>@endif
            @if($match->week)<div><dt>Hafta</dt><dd>{{ $match->week }}</dd></div>@endif
            @if($match->round)<div><dt>Tur</dt><dd>{{ $match->round }}</dd></div>@endif
            <div><dt>Durum</dt><dd x-text="statusDisplay">{{ $match->stateStatusLabel() }}</dd></div>
            @if($match->tv_broadcast)<div><dt>Yayın</dt><dd>TV yayını mevcut</dd></div>@endif
        </dl>

        @if(! empty($match->live_events))
            <div class="match-events">
                <h2>Maç olayları</h2>
                @foreach($match->live_events as $event)
                    <div class="match-event">
                        <time>{{ $event['time'] ?? '–' }}′</time>
                        <span><strong>{{ $event['label'] ?? $event['type'] ?? 'Olay' }}</strong>@if(filled($event['player_name'] ?? null)) · {{ $event['player_name'] }}@endif</span>
                        @if(filled($event['score'] ?? null))<span>{{ $event['score'] }}</span>@endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <div class="mt-3">
        <livewire:football-match-chat :football-match="$match" />
    </div>
</div>
@endsection
