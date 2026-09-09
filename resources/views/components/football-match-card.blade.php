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
            <time class="compact-match-date" datetime="{{ $match->kickoffInDisplayTimezone()->toIso8601String() }}">{{ $match->kickoffInDisplayTimezone()->locale('tr')->translatedFormat('d M') }}</time>
            <span class="compact-match-team">
                @if($logo = $match->homeTeam->logoUrl())<img src="{{ $logo }}" alt="" loading="lazy" decoding="async">@endif
                <strong @class(['winner' => $homeWinner])>{{ $match->homeTeam->resolved_name }}</strong>
            </span>
            <span class="compact-match-score">
                @if($match->home_score === null && $match->away_score === null)<strong>{{ $match->kickoffTime() }}</strong>@else<strong>{{ $match->home_score ?? '–' }} - {{ $match->away_score ?? '–' }}</strong>@endif
                <small>{{ strtolower($match->status) === 'finished' ? 'MS' : $match->statusLabel() }}</small>
            </span>
            <span class="compact-match-team compact-match-team-away">
                <strong @class(['winner' => $awayWinner])>{{ $match->awayTeam->resolved_name }}</strong>
                @if($logo = $match->awayTeam->logoUrl())<img src="{{ $logo }}" alt="" loading="lazy" decoding="async">@endif
            </span>
            <span class="compact-match-meta">{{ $match->competition->display_name ?: $match->competition->name }}</span>
            <x-ui.icon name="chevron-right" />
        </a>
    </article>
@else
<article class="match-card">
    <a class="match-card-link" href="{{ route('matches.show', $match) }}" aria-label="{{ $match->homeTeam->resolved_name }} - {{ $match->awayTeam->resolved_name }} maçını aç"></a>
    <div class="match-team">
        <x-football-team-link :team="$match->homeTeam" />
        <small class="d-block muted">{{ $match->competition->display_name ?: $match->competition->name }}</small>
    </div>
    <div class="match-score">
        @if($match->home_score === null && $match->away_score === null)
            <span>{{ $match->kickoffTime() }}</span>
        @else
            <span>{{ $match->home_score ?? '–' }} – {{ $match->away_score ?? '–' }}</span>
        @endif
        <small class="d-block {{ $match->is_live ? 'text-danger' : 'muted' }}">{{ $match->statusLabel() }}</small>
        @if($match->home_score !== null || $match->away_score !== null)
            <small class="d-block muted match-kickoff-time">{{ $match->kickoffTime() }}</small>
        @endif
    </div>
    <div class="match-team">
        <x-football-team-link :team="$match->awayTeam" :away="true" />
    </div>
</article>
@endif
