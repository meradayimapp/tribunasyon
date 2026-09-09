@extends('layouts.app')
@section('title', $match->homeTeam->resolved_name.' - '.$match->awayTeam->resolved_name)
@section('mobile-title', 'Maç Merkezi')
@section('mobile-back', route('matches.index'))
@section('content')
@php
    $homeLineup = data_get($match->lineups, 'home.starting', []);
    $awayLineup = data_get($match->lineups, 'away.starting', []);
    $hasLineups = count($homeLineup) > 0 || count($awayLineup) > 0;
@endphp
<div class="feed-column mx-auto match-center">
    <section
        class="match-detail match-center-hero surface mobile-edge"
        x-data="matchLiveState(@js(route('matches.state', $match)), @js($match->statePayload()))"
        @visibilitychange.document="visibilityChanged()"
    >
        <div class="match-detail-competition">{{ $match->competition->display_name ?: $match->competition->name }}</div>
        <div class="match-detail-scoreboard">
            <x-football-team-link :team="$match->homeTeam" class="match-detail-team" />
            <div class="match-detail-score">
                <strong>
                    <span x-show="scoreKnown" @if($match->home_score === null && $match->away_score === null) x-cloak @endif><span x-text="homeScore">{{ $match->home_score ?? '–' }}</span> - <span x-text="awayScore">{{ $match->away_score ?? '–' }}</span></span>
                    <span x-show="! scoreKnown" @if($match->home_score !== null || $match->away_score !== null) x-cloak @endif>{{ $match->kickoffTime() }}</span>
                </strong>
                <span class="match-center-state" x-text="centerStatus">{{ strtolower($match->status) === 'finished' ? 'MS' : $match->stateStatusLabel() }}</span>
                <small x-show="isLive" @if(! $match->is_live) x-cloak @endif>
                    <span x-show="minute !== null" x-text="minute + '\u2032'">{{ $match->live_minute !== null ? $match->live_minute.'′' : '' }}</span>
                    <span class="match-center-live"><i></i> Canlı</span>
                </small>
            </div>
            <x-football-team-link :team="$match->awayTeam" :away="true" class="match-detail-team" />
        </div>
        <p class="match-center-date">{{ $match->kickoffInDisplayTimezone()->translatedFormat('d F Y · l') }}</p>

        @if($match->week || $match->round || $match->venue_name || $match->referee_name || filled($match->tv_channels))
            <dl class="match-detail-facts">
                @if($match->week)<div><dt>Hafta</dt><dd>{{ $match->week }}</dd></div>@endif
                @if($match->round)<div><dt>Tur</dt><dd>{{ $match->round }}</dd></div>@endif
                @if($match->venue_name)<div><dt>Stadyum</dt><dd>{{ $match->venue_name }}</dd></div>@endif
                @if($match->referee_name)<div><dt>Hakem</dt><dd>{{ $match->referee_name }}</dd></div>@endif
                @if(filled($match->tv_channels))<div><dt>Yayın</dt><dd>{{ implode(', ', $match->tv_channels) }}</dd></div>@endif
            </dl>
        @endif

        <section class="match-events" x-ref="eventsSection" x-show="events.length > 0" @if(empty($match->live_events)) x-cloak @endif>
            <h2>Maç Olayları</h2>
            <div class="match-events-list" x-ref="eventsList">
                @foreach($match->live_events ?? [] as $event)
                    <article class="match-event side-{{ in_array($event['side'] ?? null, ['home', 'away'], true) ? $event['side'] : 'neutral' }}" data-event-type="{{ $event['type'] ?? 'event' }}">
                        <time>{{ filled($event['time'] ?? null) ? $event['time'].'′' : '–' }}</time>
                        <div class="match-event-copy">
                            <span class="match-event-symbol" aria-hidden="true"></span>
                            <span>
                                <strong>{{ $event['label'] ?? ucfirst($event['type'] ?? 'Olay') }}</strong>
                                @if(filled($event['player_name'] ?? null))<small>{{ $event['player_name'] }}</small>@endif
                                @if(filled($event['player_in'] ?? null))<small>Giren: {{ $event['player_in'] }}</small>@endif
                                @if(filled($event['player_out'] ?? null))<small>Çıkan: {{ $event['player_out'] }}</small>@endif
                            </span>
                            @if(filled($event['score'] ?? null))<b>{{ $event['score'] }}</b>@endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </section>

    @if($hasLineups)
        <section class="match-center-section surface mobile-edge" aria-labelledby="lineups-title">
            <div class="match-center-section-heading">
                <h2 id="lineups-title">İlk 11</h2>
                @if($match->lineup_is_projected)<span>Tahmini</span>@endif
            </div>
            <div class="match-lineups">
                @foreach(['home' => $match->homeTeam, 'away' => $match->awayTeam] as $side => $team)
                    @php($players = data_get($match->lineups, $side.'.starting', []))
                    <div class="match-lineup-team">
                        <div class="match-lineup-team-heading">
                            @if($team->logo_url)<img src="{{ $team->logo_url }}" alt="" aria-hidden="true">@endif
                            <strong>{{ $team->resolved_name }}</strong>
                            @if(filled(data_get($match->lineups, 'formation.'.$side)))<span>{{ data_get($match->lineups, 'formation.'.$side) }}</span>@endif
                        </div>
                        <ol class="match-lineup-list">
                            @foreach($players as $player)
                                <li>
                                    <span class="match-lineup-number">{{ $player['number'] ?? '–' }}</span>
                                    @if(filled($player['image'] ?? null))<img src="{{ $player['image'] }}" alt="" loading="lazy">@endif
                                    <span><strong>{{ $player['name'] ?? 'Oyuncu' }}</strong>@if(filled($player['position'] ?? null))<small>{{ $player['position'] }}</small>@endif</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if(filled($match->match_stats))
        <section class="match-center-section match-statistics surface mobile-edge" aria-labelledby="stats-title">
            <div class="match-center-section-heading"><h2 id="stats-title">Maç İstatistikleri</h2></div>
            <div class="match-statistics-list">
                @foreach($match->match_stats as $stat)
                    <div><strong>{{ $stat['home'] }}</strong><span>{{ $stat['label'] }}</span><strong>{{ $stat['away'] }}</strong></div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="mt-3">
        <livewire:football-match-chat :football-match="$match" />
    </div>
</div>
@endsection
