@php
    $scoreKnown = $match->home_score !== null || $match->away_score !== null;
    $initialLabel = $match->is_live
        ? ($match->displayMinute() !== null ? 'CANLI '.$match->displayMinute().'′' : 'CANLI')
        : ($match->isCompleted() ? 'MS' : $match->statusLabel());
@endphp
<article class="competition-match-row" data-match-state data-live-match-id="{{ $match->id }}" @class(['is-live' => $match->is_live])>
    <a href="{{ route('matches.show', $match) }}" aria-label="{{ $match->homeTeam->resolved_name }} - {{ $match->awayTeam->resolved_name }} maç merkezini aç">
        <time datetime="{{ $match->kickoffInDisplayTimezone()->toIso8601String() }}">{{ $match->kickoffTime() }}</time>
        <span class="competition-match-clubs">
            <span><strong>{{ $match->homeTeam->resolved_name }}</strong>@if($logo = $match->homeTeam->logoUrl())<img src="{{ $logo }}" alt="" loading="lazy" decoding="async">@endif</span>
            <span><strong>{{ $match->awayTeam->resolved_name }}</strong>@if($logo = $match->awayTeam->logoUrl())<img src="{{ $logo }}" alt="" loading="lazy" decoding="async">@endif</span>
        </span>
        <span class="competition-match-result">
            <strong x-show="matchScore({{ $match->id }}, {{ $scoreKnown ? 'true' : 'false' }}, @js($match->home_score), @js($match->away_score)) !== null" x-text="matchScore({{ $match->id }}, {{ $scoreKnown ? 'true' : 'false' }}, @js($match->home_score), @js($match->away_score))">@if($scoreKnown){{ $match->home_score ?? '–' }} - {{ $match->away_score ?? '–' }}@endif</strong>
            <small :class="{ 'is-live': isLive({{ $match->id }}, {{ $match->is_live ? 'true' : 'false' }}) }" x-text="matchStatus({{ $match->id }}, @js($initialLabel))">{{ $initialLabel }}</small>
        </span>
        <x-ui.icon name="chevron-right" />
    </a>
</article>
