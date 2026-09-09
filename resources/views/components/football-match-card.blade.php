@props(['match'])
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
