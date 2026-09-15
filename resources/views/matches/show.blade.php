@extends('layouts.app')
@section('title', $match->homeTeam->resolved_name.' - '.$match->awayTeam->resolved_name)
@section('mobile-title', 'Maç Merkezi')
@section('mobile-back', route('matches.index'))
@section('content')
@php
    $initialTab = $match->is_live ? 'sohbet' : 'ozet';
    $initialState = $match->statePayload();
    $initialEvents = $initialState['events'];
    $initialStats = $initialState['stats'];
    $initialHomeLineup = data_get($initialState, 'lineups.home.starting', []);
    $initialAwayLineup = data_get($initialState, 'lineups.away.starting', []);
    $hasInitialLineups = (is_array($initialHomeLineup) && filled($initialHomeLineup))
        || (is_array($initialAwayLineup) && filled($initialAwayLineup));
    $hasInjuries = filled($injuries['home'] ?? []) || filled($injuries['away'] ?? []);
@endphp
<div class="feed-column mx-auto match-center"
    x-data="matchLiveState(@js(route('matches.state', $match)), @js($initialState), @js($initialTab))"
    @visibilitychange.document="visibilityChanged()" @hashchange.window="hashChanged()">
    <section @class(['match-detail', 'match-center-hero', 'surface', 'mobile-edge', 'is-live' => $match->is_live]) :class="{ 'is-live': isLive }">
        <div class="match-detail-competition"><x-ui.icon name="trophy" /><span>{{ $match->competition->display_name ?: $match->competition->name }}</span></div>
        <div class="match-detail-scoreboard">
            <x-football-team-link :team="$match->homeTeam" class="match-detail-team" />
            <div class="match-detail-score" aria-live="polite">
                <strong>
                    <span x-show="isLive || scoreKnown" @if(! $match->is_live && $match->home_score === null && $match->away_score === null) x-cloak @endif>
                        <span x-text="homeScore ?? '–'">{{ $match->home_score ?? '–' }}</span><span class="match-score-separator">–</span><span x-text="awayScore ?? '–'">{{ $match->away_score ?? '–' }}</span>
                    </span>
                    <span x-show="! isLive && ! scoreKnown" @if($match->is_live || $match->home_score !== null || $match->away_score !== null) x-cloak @endif>{{ $match->kickoffTime() }}</span>
                </strong>
                <span class="match-center-status" :class="{ 'is-live': isLive, 'is-finished': isFinished }"><i x-show="isLive" @if(! $match->is_live) x-cloak @endif aria-hidden="true"></i><span x-text="centerStatus">{{ $match->statusLabel() }}</span></span>
                <small class="match-center-minute" x-show="isLive && minute !== null" @if(! $match->is_live || $match->displayMinute() === null) x-cloak @endif x-text="minute + '′'">{{ $match->displayMinute() !== null ? $match->displayMinute().'′' : '' }}</small>
            </div>
            <x-football-team-link :team="$match->awayTeam" :away="true" class="match-detail-team" />
        </div>
        @if(filled(data_get($match->lineups, 'home.coach.name')) || filled(data_get($match->lineups, 'away.coach.name')))
            <p class="match-center-coaches">
                @if(filled(data_get($match->lineups, 'home.coach.name')))<span>{{ $match->homeTeam->resolved_name }}: {{ data_get($match->lineups, 'home.coach.name') }}</span>@endif
                @if(filled(data_get($match->lineups, 'away.coach.name')))<span>{{ $match->awayTeam->resolved_name }}: {{ data_get($match->lineups, 'away.coach.name') }}</span>@endif
            </p>
        @endif
        <p class="match-center-date"><x-ui.icon name="calendar" /><time datetime="{{ $match->kickoffInDisplayTimezone()->toIso8601String() }}">{{ $match->kickoffInDisplayTimezone()->translatedFormat('d F Y · l') }}</time></p>
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

    <nav class="match-details-tabs match-center-tabs" role="tablist" aria-label="Maç Merkezi bölümleri" x-ref="tabList" @keydown="tabKeydown($event)">
        @foreach(['sohbet' => 'Sohbet', 'ozet' => 'Özet', 'istatistik' => 'İstatistik', 'kadro' => 'Kadro', 'puan-durumu' => 'Puan Durumu', 'h2h' => 'H2H'] as $key => $label)
            <button type="button" id="match-tab-{{ $key }}" role="tab" aria-controls="match-panel-{{ $key }}"
                @click="selectTab('{{ $key }}')" :class="{ active: activeTab === '{{ $key }}' }"
                :aria-selected="(activeTab === '{{ $key }}').toString()" :tabindex="activeTab === '{{ $key }}' ? 0 : -1"
                >{{ $label }}</button>
        @endforeach
    </nav>

    <section id="match-panel-sohbet" class="match-center-tab-panel" role="tabpanel" aria-labelledby="match-tab-sohbet" x-show="activeTab === 'sohbet'" @if($initialTab !== 'sohbet') x-cloak @endif>
        <livewire:football-match-chat :football-match="$match" />
    </section>

    <section id="match-panel-ozet" class="match-center-tab-panel surface mobile-edge" role="tabpanel" aria-labelledby="match-tab-ozet" x-show="activeTab === 'ozet'" @if($initialTab !== 'ozet') x-cloak @endif>
        <div class="match-panel-heading"><div><span class="match-panel-icon"><x-ui.icon name="football" /></span><div><h2>Maç Olayları</h2><p>Maçın önemli anları</p></div></div></div>
        <div class="match-events-list" x-ref="eventsList" x-show="events.length > 0" @if(blank($initialEvents)) x-cloak @endif>
            @foreach($initialEvents as $event)
                <article class="match-event side-{{ in_array($event['side'] ?? null, ['home', 'away'], true) ? $event['side'] : 'neutral' }}" data-event-type="{{ $event['type'] ?? 'event' }}">
                    <div class="match-event-node"><time>{{ filled($event['time'] ?? null) ? $event['time'].'′' : '–' }}</time><span class="match-event-symbol" aria-hidden="true"></span></div>
                    <div class="match-event-content"><span class="match-event-copy"><strong>{{ $event['label'] ?? 'Olay' }}</strong>
                        @if(filled($event['player_name'] ?? null))<small>{{ $event['player_name'] }}</small>@endif
                        @if(filled($event['player_in'] ?? null))<small class="player-in">Giren · {{ $event['player_in'] }}</small>@endif
                        @if(filled($event['player_out'] ?? null))<small class="player-out">Çıkan · {{ $event['player_out'] }}</small>@endif
                    </span>@if(filled($event['score'] ?? null))<b>{{ $event['score'] }}</b>@endif</div>
                </article>
            @endforeach
        </div>
        <div class="match-panel-empty" x-show="events.length === 0" @if(filled($initialEvents)) x-cloak @endif><strong>Henüz önemli bir olay yok.</strong><small>Gerçek maç olayları geldiğinde burada görünecek.</small></div>
    </section>

    <section id="match-panel-istatistik" class="match-center-tab-panel surface mobile-edge" role="tabpanel" aria-labelledby="match-tab-istatistik" x-show="activeTab === 'istatistik'" x-cloak>
        <div class="match-panel-heading"><div><span class="match-panel-icon"><x-ui.icon name="activity" /></span><div><h2>Maç İstatistikleri</h2><p>Takımların saha içi karşılaştırması</p></div></div></div>
        <div class="match-statistics-list" x-ref="statsList" x-show="stats.length > 0" @if(blank($initialStats)) x-cloak @endif>
            @foreach($initialStats as $stat)
                @php($share = max(0, min(100, (float) ($stat['home_share'] ?? 50))))
                <div class="match-statistic" style="--home-share:{{ $share }}%;--away-share:{{ 100 - $share }}%">
                    <div class="match-statistic-values"><strong>{{ $stat['home'] ?? '–' }}</strong><span>{{ $stat['label'] ?? 'İstatistik' }}</span><strong>{{ $stat['away'] ?? '–' }}</strong></div>
                    <div class="match-statistic-track" aria-hidden="true"><i class="home"></i><i class="away"></i></div>
                </div>
            @endforeach
        </div>
        <div class="match-panel-empty" x-show="stats.length === 0" @if(filled($initialStats)) x-cloak @endif><strong>Maç istatistikleri henüz mevcut değil.</strong></div>
    </section>

    <section id="match-panel-kadro" class="match-center-tab-panel surface mobile-edge" role="tabpanel" aria-labelledby="match-tab-kadro" x-show="activeTab === 'kadro'" x-cloak>
        <div class="match-panel-heading"><div><span class="match-panel-icon"><x-ui.icon name="teams" /></span><div><h2>Kadro</h2><p x-text="lineupIsProjected ? 'Tahmini kadrolar' : 'Açıklanan kadrolar'">{{ $match->lineup_is_projected ? 'Tahmini kadrolar' : 'Açıklanan kadrolar' }}</p></div></div></div>
        <div class="match-lineup-switch" role="group" aria-label="Takım kadrosu seç">
            <button type="button" @click="lineupSide = 'home'" :class="{ active: lineupSide === 'home' }" :aria-pressed="(lineupSide === 'home').toString()">{{ $match->homeTeam->resolved_name }}</button>
            <button type="button" @click="lineupSide = 'away'" :class="{ active: lineupSide === 'away' }" :aria-pressed="(lineupSide === 'away').toString()">{{ $match->awayTeam->resolved_name }}</button>
        </div>
        <div class="match-lineups" x-ref="lineupsList" x-show="hasLineups" @if(! $hasInitialLineups) x-cloak @endif>
            <div x-show="lineupSide === 'home'">@include('matches.partials.lineup-team', ['side' => 'home', 'team' => $match->homeTeam])</div>
            <div x-show="lineupSide === 'away'" x-cloak>@include('matches.partials.lineup-team', ['side' => 'away', 'team' => $match->awayTeam])</div>
        </div>
        <div class="match-panel-empty" x-show="!hasLineups" @if($hasInitialLineups) x-cloak @endif><strong>Kadro bilgisi henüz açıklanmadı.</strong></div>
        @if($hasInjuries)
            <div class="match-injuries"><h3>Sakat ve Cezalılar</h3>
                @foreach(['home' => $match->homeTeam, 'away' => $match->awayTeam] as $side => $team)
                    @if(filled($injuries[$side] ?? []))
                        <h4>{{ $team->resolved_name }}</h4>
                        <ul>@foreach($injuries[$side] as $player)<li><strong>{{ $player['name'] }}</strong><span>{{ $player['status'] }}</span></li>@endforeach</ul>
                    @endif
                @endforeach
            </div>
        @endif
    </section>

    <section id="match-panel-puan-durumu" class="match-center-tab-panel surface mobile-edge standings-content" role="tabpanel" aria-labelledby="match-tab-puan-durumu" x-show="activeTab === 'puan-durumu'" x-cloak>
        @if($tables->isEmpty())
            <div class="match-panel-empty"><strong>Puan durumu şu anda görüntülenemiyor.</strong></div>
        @else
            <div class="standings-heading"><div><span>{{ $match->competition->display_name ?: $match->competition->name }}</span><h2>Puan Durumu</h2></div>@if($standingsSeason)<strong>{{ $standingsSeason }}</strong>@endif</div>
            @include('teams.partials.standings-table')
        @endif
    </section>

    <section id="match-panel-h2h" class="match-center-tab-panel surface mobile-edge" role="tabpanel" aria-labelledby="match-tab-h2h" x-show="activeTab === 'h2h'" x-cloak>
        <div class="match-panel-heading"><div><span class="match-panel-icon"><x-ui.icon name="football" /></span><div><h2>Karşılaştırma</h2><p>Takımların gerçek maç geçmişi</p></div></div></div>
        @if($h2h && (filled($h2h['history']) || filled($h2h['home_form']) || filled($h2h['away_form']) || $h2h['summary']))
            <div class="match-h2h-content">
                <h3>Son 5 Form</h3>
                @foreach(['home' => $match->homeTeam, 'away' => $match->awayTeam] as $side => $team)
                    <div class="match-h2h-form"><strong>{{ $team->resolved_name }}</strong><span>
                        @forelse($h2h[$side.'_form'] as $result)<i class="form-result form-{{ $result === 'W' ? 'win' : ($result === 'D' ? 'draw' : 'loss') }}" aria-label="{{ $result === 'W' ? 'Galibiyet' : ($result === 'D' ? 'Beraberlik' : 'Mağlubiyet') }}">{{ $result }}</i>@empty — @endforelse
                    </span></div>
                @endforeach
                @if(filled($h2h['history']))<h3>Son Karşılaşmalar</h3><ol class="match-h2h-history">
                    @foreach(array_slice($h2h['history'], 0, 5) as $past)<li><span>{{ $past['home_name'] }} <strong>{{ $past['score'] }}</strong> {{ $past['away_name'] }}</span>@if($past['date'])<time>{{ $past['date'] }}</time>@endif</li>@endforeach
                </ol>@endif
                @if($h2h['summary'])<h3>Toplam</h3><div class="match-h2h-summary"><span>{{ $match->homeTeam->resolved_name }} <strong>{{ $h2h['summary']['home_wins'] }}</strong></span><span>Beraberlik <strong>{{ $h2h['summary']['draws'] }}</strong></span><span>{{ $match->awayTeam->resolved_name }} <strong>{{ $h2h['summary']['away_wins'] }}</strong></span></div>@endif
            </div>
        @else<div class="match-panel-empty"><strong>Karşılaştırma verisi bulunamadı.</strong></div>@endif
    </section>
</div>
@endsection
