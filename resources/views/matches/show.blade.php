@extends('layouts.app')
@section('title', $match->homeTeam->resolved_name.' - '.$match->awayTeam->resolved_name)
@section('mobile-title', 'Maç Merkezi')
@section('mobile-back', route('matches.index'))
@section('content')
@php
    $homeLineup = data_get($match->lineups, 'home.starting', []);
    $awayLineup = data_get($match->lineups, 'away.starting', []);
    $hasLineups = count($homeLineup) > 0 || count($awayLineup) > 0;
    $hasStats = filled($match->match_stats);
    $hasEvents = filled($match->live_events);
    $showDetails = $match->is_live || $hasEvents || $hasStats || $hasLineups;
    $statusDisplay = mb_strtolower((string) $match->status_display);
    $isHalfTime = $match->is_live && (
        str_contains($statusDisplay, 'devre')
        || str_contains($statusDisplay, 'half')
        || in_array(mb_strtoupper((string) $match->status_display), ['HT', 'İY'], true)
    );
    $initialCenterStatus = $match->isFinished()
        ? 'Bitti'
        : ($match->is_live ? ($isHalfTime ? 'Devre Arası' : 'Canlı') : $match->statusLabel());
    $statNumber = static function (mixed $value): float {
        if (! is_numeric($value)) {
            preg_match('/-?\d+(?:[.,]\d+)?/', (string) $value, $matches);
            $value = $matches[0] ?? 0;
        }

        return max(0, (float) str_replace(',', '.', (string) $value));
    };
@endphp
<div
    class="feed-column mx-auto match-center"
    x-data="matchLiveState(@js(route('matches.state', $match)), @js($match->statePayload()))"
    @visibilitychange.document="visibilityChanged()"
    @keyup.escape.window="closePanel()"
>
    <section @class(['match-detail', 'match-center-hero', 'surface', 'mobile-edge', 'is-live' => $match->is_live]) :class="{ 'is-live': isLive }">
        <div class="match-detail-competition">
            <x-ui.icon name="trophy" />
            <span>{{ $match->competition->display_name ?: $match->competition->name }}</span>
        </div>

        <div class="match-detail-scoreboard">
            <x-football-team-link :team="$match->homeTeam" class="match-detail-team" />
            <div class="match-detail-score" aria-live="polite">
                <strong>
                    <span x-show="isLive || scoreKnown" @if(! $match->is_live && $match->home_score === null && $match->away_score === null) x-cloak @endif>
                        <span x-text="homeScore ?? '–'">{{ $match->home_score ?? '–' }}</span>
                        <span class="match-score-separator">–</span>
                        <span x-text="awayScore ?? '–'">{{ $match->away_score ?? '–' }}</span>
                    </span>
                    <span x-show="! isLive && ! scoreKnown" @if($match->is_live || $match->home_score !== null || $match->away_score !== null) x-cloak @endif>{{ $match->kickoffTime() }}</span>
                </strong>
                <span class="match-center-status" :class="{ 'is-live': isLive, 'is-finished': isFinished }">
                    <i x-show="isLive" @if(! $match->is_live) x-cloak @endif aria-hidden="true"></i>
                    <span x-text="centerStatus">{{ $initialCenterStatus }}</span>
                </span>
                <small
                    class="match-center-minute"
                    x-show="isLive && minute !== null"
                    @if(! $match->is_live || $match->displayMinute() === null) x-cloak @endif
                    x-text="minute + '′'"
                >{{ $match->displayMinute() !== null ? $match->displayMinute().'′' : '' }}</small>
            </div>
            <x-football-team-link :team="$match->awayTeam" :away="true" class="match-detail-team" />
        </div>

        <p class="match-center-date">
            <x-ui.icon name="calendar" />
            <time datetime="{{ $match->kickoffInDisplayTimezone()->toIso8601String() }}">{{ $match->kickoffInDisplayTimezone()->translatedFormat('d F Y · l') }}</time>
        </p>

        @if($match->week || $match->round || $match->venue_name || $match->referee_name || filled($match->tv_channels))
            <dl class="match-detail-facts" aria-label="Maç bilgileri">
                @if($match->week)<div><dt>Hafta</dt><dd>{{ $match->week }}</dd></div>@endif
                @if($match->round)<div><dt>Tur</dt><dd>{{ $match->round }}</dd></div>@endif
                @if($match->venue_name)<div><dt>Stadyum</dt><dd>{{ $match->venue_name }}</dd></div>@endif
                @if($match->referee_name)<div><dt>Hakem</dt><dd>{{ $match->referee_name }}</dd></div>@endif
                @if(filled($match->tv_channels))<div><dt>Yayın</dt><dd>{{ implode(', ', $match->tv_channels) }}</dd></div>@endif
            </dl>
        @endif
    </section>

    <div class="match-chat-priority">
        <livewire:football-match-chat :football-match="$match" />
    </div>

    @if($showDetails)
        <section class="match-details-drawer surface mobile-edge" aria-label="Maçın ayrıntılı verileri">
            <header class="match-details-drawer-heading">
                <div>
                    <span class="eyebrow">Maçın hikâyesi</span>
                    <h2>Detaylara göz at</h2>
                </div>
                <small>İstediğin bölümü aç</small>
            </header>

            <div class="match-details-tabs" role="tablist" aria-label="Maç detayı bölümleri">
                @if($match->is_live || $hasEvents)
                    <button
                        type="button"
                        role="tab"
                        @click="togglePanel('events')"
                        :class="{ 'active': activePanel === 'events' }"
                        :aria-selected="activePanel === 'events'"
                        :aria-expanded="activePanel === 'events'"
                        aria-controls="match-panel-events"
                    >
                        <x-ui.icon name="football" />
                        <span>Olaylar</span>
                        <small x-text="events.length">{{ count($match->live_events ?? []) }}</small>
                    </button>
                @endif
                @if($hasStats)
                    <button
                        type="button"
                        role="tab"
                        @click="togglePanel('stats')"
                        :class="{ 'active': activePanel === 'stats' }"
                        :aria-selected="activePanel === 'stats'"
                        :aria-expanded="activePanel === 'stats'"
                        aria-controls="match-panel-stats"
                    >
                        <x-ui.icon name="activity" />
                        <span>İstatistikler</span>
                    </button>
                @endif
                @if($hasLineups)
                    <button
                        type="button"
                        role="tab"
                        @click="togglePanel('lineups')"
                        :class="{ 'active': activePanel === 'lineups' }"
                        :aria-selected="activePanel === 'lineups'"
                        :aria-expanded="activePanel === 'lineups'"
                        aria-controls="match-panel-lineups"
                    >
                        <x-ui.icon name="teams" />
                        <span>İlk 11</span>
                    </button>
                @endif
            </div>

            @if($match->is_live || $hasEvents)
                <div
                    id="match-panel-events"
                    class="match-detail-panel"
                    role="tabpanel"
                    x-cloak
                    x-show="activePanel === 'events'"
                    x-transition:enter="match-panel-enter"
                    x-transition:enter-start="match-panel-enter-start"
                    x-transition:enter-end="match-panel-enter-end"
                    x-transition:leave="match-panel-leave"
                    x-transition:leave-start="match-panel-leave-start"
                    x-transition:leave-end="match-panel-leave-end"
                >
                    <div class="match-panel-heading">
                        <div><span class="match-panel-icon"><x-ui.icon name="football" /></span><div><h3>Maç Olayları</h3><p>Sahadaki önemli anlar</p></div></div>
                        <button type="button" @click="closePanel()" aria-label="Maç olaylarını kapat"><x-ui.icon name="close" /></button>
                    </div>

                    <div class="match-events-list" x-ref="eventsList" x-show="events.length > 0" @if(! $hasEvents) x-cloak @endif>
                        @foreach($match->live_events ?? [] as $event)
                            @php
                                $eventType = in_array($event['type'] ?? null, ['goal', 'yellow_card', 'red_card', 'substitution'], true)
                                    ? $event['type']
                                    : 'event';
                            @endphp
                            <article class="match-event side-{{ in_array($event['side'] ?? null, ['home', 'away'], true) ? $event['side'] : 'neutral' }}" data-event-type="{{ $eventType }}">
                                <div class="match-event-node">
                                    <time>{{ filled($event['time'] ?? null) ? $event['time'].'′' : '–' }}</time>
                                    <span class="match-event-symbol" aria-hidden="true"></span>
                                </div>
                                <div class="match-event-content">
                                    <span class="match-event-copy">
                                        <strong>{{ $event['label'] ?? ucfirst($event['type'] ?? 'Olay') }}</strong>
                                        @if(filled($event['player_name'] ?? null))<small>{{ $event['player_name'] }}</small>@endif
                                        @if(filled($event['player_in'] ?? null))<small class="player-in">Giren · {{ $event['player_in'] }}</small>@endif
                                        @if(filled($event['player_out'] ?? null))<small class="player-out">Çıkan · {{ $event['player_out'] }}</small>@endif
                                    </span>
                                    @if(filled($event['score'] ?? null))<b>{{ $event['score'] }}</b>@endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <div class="match-panel-empty" x-show="events.length === 0" @if($hasEvents) x-cloak @endif>
                        <span class="match-panel-icon"><x-ui.icon name="activity" /></span>
                        <strong>Henüz önemli bir olay yok.</strong>
                        <small>Maç ilerledikçe gol ve kartlar burada görünecek.</small>
                    </div>
                </div>
            @endif

            @if($hasStats)
                <div
                    id="match-panel-stats"
                    class="match-detail-panel"
                    role="tabpanel"
                    x-cloak
                    x-show="activePanel === 'stats'"
                    x-transition:enter="match-panel-enter"
                    x-transition:enter-start="match-panel-enter-start"
                    x-transition:enter-end="match-panel-enter-end"
                    x-transition:leave="match-panel-leave"
                    x-transition:leave-start="match-panel-leave-start"
                    x-transition:leave-end="match-panel-leave-end"
                >
                    <div class="match-panel-heading">
                        <div><span class="match-panel-icon"><x-ui.icon name="activity" /></span><div><h3 id="stats-title">Maç İstatistikleri</h3><p>Takımların saha içi karşılaştırması</p></div></div>
                        <button type="button" @click="closePanel()" aria-label="İstatistikleri kapat"><x-ui.icon name="close" /></button>
                    </div>
                    <div class="match-statistics-list" aria-labelledby="stats-title">
                        @foreach($match->match_stats as $stat)
                            @php
                                $homeValue = $statNumber($stat['home'] ?? 0);
                                $awayValue = $statNumber($stat['away'] ?? 0);
                                $totalValue = $homeValue + $awayValue;
                                $homeShare = $totalValue > 0 ? round(($homeValue / $totalValue) * 100, 2) : 50;
                                $awayShare = 100 - $homeShare;
                            @endphp
                            <div class="match-statistic" style="--home-share: {{ $homeShare }}%; --away-share: {{ $awayShare }}%;">
                                <div class="match-statistic-values">
                                    <strong>{{ $stat['home'] ?? '–' }}</strong>
                                    <span>{{ $stat['label'] ?? 'İstatistik' }}</span>
                                    <strong>{{ $stat['away'] ?? '–' }}</strong>
                                </div>
                                <div class="match-statistic-track" aria-hidden="true">
                                    <i class="home"></i><i class="away"></i>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($hasLineups)
                <div
                    id="match-panel-lineups"
                    class="match-detail-panel"
                    role="tabpanel"
                    x-cloak
                    x-show="activePanel === 'lineups'"
                    x-transition:enter="match-panel-enter"
                    x-transition:enter-start="match-panel-enter-start"
                    x-transition:enter-end="match-panel-enter-end"
                    x-transition:leave="match-panel-leave"
                    x-transition:leave-start="match-panel-leave-start"
                    x-transition:leave-end="match-panel-leave-end"
                >
                    <div class="match-panel-heading">
                        <div><span class="match-panel-icon"><x-ui.icon name="teams" /></span><div><h3 id="lineups-title">İlk 11</h3><p>{{ $match->lineup_is_projected ? 'Tahmini kadrolar' : 'Başlangıç kadroları' }}</p></div></div>
                        <button type="button" @click="closePanel()" aria-label="İlk 11'i kapat"><x-ui.icon name="close" /></button>
                    </div>
                    <div class="match-lineups" aria-labelledby="lineups-title">
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
                </div>
            @endif
        </section>
    @endif
</div>
@endsection
