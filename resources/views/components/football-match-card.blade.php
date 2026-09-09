@props(['match', 'variant' => 'default'])

@if($variant === 'featured')
    <article class="featured-match-card">
        <a class="featured-match-link" href="{{ route('matches.show', $match) }}" aria-label="{{ $match->homeTeam->resolved_name }} - {{ $match->awayTeam->resolved_name }} maçını aç">
            <span class="featured-match-label"><x-ui.icon name="sparkle" /> Sonraki Maç</span>
            <span class="featured-match-competition">{{ $match->competition->display_name ?: $match->competition->name }}</span>
            <span class="featured-match-teams">
                <span class="featured-match-team">
                    @if($logo = $match->homeTeam->logoUrl())<img src="{{ $logo }}" alt="" loading="lazy" decoding="async">@else<span class="featured-match-logo-fallback">{{ mb_strtoupper(mb_substr($match->homeTeam->resolved_name, 0, 2)) }}</span>@endif
                    <strong>{{ $match->homeTeam->resolved_name }}</strong>
                </span>
                <span class="featured-match-kickoff"><strong>{{ $match->kickoffTime() }}</strong><time datetime="{{ $match->kickoffInDisplayTimezone()->toIso8601String() }}">{{ $match->kickoffInDisplayTimezone()->locale('tr')->translatedFormat('d F') }}</time><small>{{ $match->statusLabel() }}</small></span>
                <span class="featured-match-team featured-match-team-away">
                    @if($logo = $match->awayTeam->logoUrl())<img src="{{ $logo }}" alt="" loading="lazy" decoding="async">@else<span class="featured-match-logo-fallback">{{ mb_strtoupper(mb_substr($match->awayTeam->resolved_name, 0, 2)) }}</span>@endif
                    <strong>{{ $match->awayTeam->resolved_name }}</strong>
                </span>
            </span>
            <span class="featured-match-cta">Maç detayına git <x-ui.icon name="chevron-right" /></span>
        </a>
    </article>
@elseif($variant === 'compact')
    @php
        $finishedWithScore = strtolower($match->status) === 'finished' && $match->home_score !== null && $match->away_score !== null;
        $homeWinner = $finishedWithScore && $match->home_score > $match->away_score;
        $awayWinner = $finishedWithScore && $match->away_score > $match->home_score;
    @endphp
    <article class="compact-match-card">
        <a href="{{ route('matches.show', $match) }}" aria-label="{{ $match->homeTeam->resolved_name }} - {{ $match->awayTeam->resolved_name }} maçını aç">
            <time class="compact-match-date" datetime="{{ $match->kickoffInDisplayTimezone()->toIso8601String() }}">
                <strong>{{ $match->kickoffInDisplayTimezone()->format('d') }}</strong>
                <small>{{ $match->kickoffInDisplayTimezone()->locale('tr')->translatedFormat('M') }}</small>
            </time>
            <span class="compact-match-clubs">
                <span class="compact-match-club">
                    @if($logo = $match->homeTeam->logoUrl())<img src="{{ $logo }}" alt="" loading="lazy" decoding="async">@else<span class="compact-match-club-fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr($match->homeTeam->resolved_name, 0, 1)) }}</span>@endif
                    <strong @class(['winner' => $homeWinner])>{{ $match->homeTeam->resolved_name }}</strong>
                </span>
                <span class="compact-match-club">
                    @if($logo = $match->awayTeam->logoUrl())<img src="{{ $logo }}" alt="" loading="lazy" decoding="async">@else<span class="compact-match-club-fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr($match->awayTeam->resolved_name, 0, 1)) }}</span>@endif
                    <strong @class(['winner' => $awayWinner])>{{ $match->awayTeam->resolved_name }}</strong>
                </span>
                <small class="compact-match-meta">{{ $match->competition->display_name ?: $match->competition->name }}</small>
            </span>
            <span class="compact-match-score">
                @if($match->home_score === null && $match->away_score === null)<strong>{{ $match->kickoffTime() }}</strong>@else<strong>{{ $match->home_score ?? '–' }} - {{ $match->away_score ?? '–' }}</strong>@endif
                <small>{{ strtolower($match->status) === 'finished' ? 'MS' : $match->statusLabel() }}</small>
            </span>
            <x-ui.icon name="chevron-right" />
        </a>
    </article>
@else
    @php
        $isFinished = strtolower($match->status) === 'finished';
        $isScheduled = in_array(strtolower($match->status), ['scheduled', 'not_started'], true);
        $liveMinute = $match->displayMinute();
        $statusDisplay = mb_strtolower((string) $match->status_display);
        $isHalfTime = $match->is_live && (
            str_contains($statusDisplay, 'devre')
            || str_contains($statusDisplay, 'half')
            || in_array(mb_strtoupper((string) $match->status_display), ['HT', 'İY'], true)
        );
    @endphp
    <article @class(['match-card', 'is-live' => $match->is_live, 'is-finished' => $isFinished])>
        <a class="match-card-link" href="{{ route('matches.show', $match) }}" aria-label="{{ $match->homeTeam->resolved_name }} - {{ $match->awayTeam->resolved_name }} maçını aç"></a>

        <header class="match-card-header">
            <span>{{ $match->competition->display_name ?: $match->competition->name }}</span>
            @if($match->is_live)
                <span class="match-card-live-label"><i></i>{{ $isHalfTime ? 'Devre Arası' : 'Canlı' }}</span>
            @else
                <time datetime="{{ $match->kickoffInDisplayTimezone()->toIso8601String() }}">{{ $match->kickoffInDisplayTimezone()->locale('tr')->translatedFormat('d M') }}</time>
            @endif
        </header>

        <div class="match-card-stage">
            <div class="match-card-team match-card-team-home">
                <x-football-team-link :team="$match->homeTeam" />
            </div>

            <div class="match-card-score" aria-label="Maç durumu">
                @if($match->is_live)
                    <strong>{{ $match->home_score ?? '–' }} <span>–</span> {{ $match->away_score ?? '–' }}</strong>
                    @if($liveMinute !== null)<small class="match-card-running">{{ $liveMinute }}′</small>@endif
                @elseif($isFinished)
                    <strong>{{ $match->home_score ?? '–' }} <span>–</span> {{ $match->away_score ?? '–' }}</strong>
                    <small>Bitti</small>
                @elseif($isScheduled)
                    <strong>{{ $match->kickoffTime() }}</strong>
                    <small>Başlamadı</small>
                @else
                    @if($match->home_score !== null || $match->away_score !== null)
                        <strong>{{ $match->home_score ?? '–' }} <span>–</span> {{ $match->away_score ?? '–' }}</strong>
                    @endif
                    <small>{{ $match->statusLabel() }}</small>
                @endif
            </div>

            <div class="match-card-team match-card-team-away">
                <x-football-team-link :team="$match->awayTeam" :away="true" />
            </div>
        </div>

        <span class="match-card-open" aria-hidden="true"><x-ui.icon name="chevron-right" /></span>
    </article>
@endif
