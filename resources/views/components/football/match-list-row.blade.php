@props(['match'])
@php
    $scoreKnown = $match->home_score !== null || $match->away_score !== null;
    $initialPrimary = $scoreKnown
        ? ($match->home_score ?? '–').' - '.($match->away_score ?? '–')
        : '- : -';
    $initialStatus = $match->matchListStatusLabel();
    $homeName = $match->homeTeam->resolved_name;
    $awayName = $match->awayTeam->resolved_name;
@endphp
<article
    data-match-state
    data-live-match-id="{{ $match->id }}"
    @class(['matches-list-row', 'is-live' => $match->is_live])
    :class="{ 'is-live': isLive({{ $match->id }}, {{ $match->is_live ? 'true' : 'false' }}) }"
>
    <a href="{{ route('matches.show', $match) }}" aria-label="{{ $homeName }} - {{ $awayName }} maç merkezini aç">
        <time datetime="{{ $match->kickoffInDisplayTimezone()->toIso8601String() }}">{{ $match->kickoffTime() }}</time>
        <span class="matches-list-team matches-list-team-home">
            <strong title="{{ $homeName }}">{{ $homeName }}</strong>
            <span class="matches-list-logo">
                @if($logo = $match->homeTeam->logoUrl())
                    <img src="{{ $logo }}" alt="" loading="lazy" decoding="async">
                @else
                    <span aria-hidden="true">{{ mb_strtoupper(mb_substr($homeName, 0, 2), 'UTF-8') }}</span>
                @endif
            </span>
        </span>
        <span class="matches-list-score">
            <strong x-text="matchPrimary({{ $match->id }}, @js($initialPrimary))">{{ $initialPrimary }}</strong>
            <small
                :class="{ 'is-live': isLive({{ $match->id }}, {{ $match->is_live ? 'true' : 'false' }}) }"
                x-text="matchListStatus({{ $match->id }}, @js($initialStatus))"
            >{{ $initialStatus }}</small>
        </span>
        <span class="matches-list-team matches-list-team-away">
            <span class="matches-list-logo">
                @if($logo = $match->awayTeam->logoUrl())
                    <img src="{{ $logo }}" alt="" loading="lazy" decoding="async">
                @else
                    <span aria-hidden="true">{{ mb_strtoupper(mb_substr($awayName, 0, 2), 'UTF-8') }}</span>
                @endif
            </span>
            <strong title="{{ $awayName }}">{{ $awayName }}</strong>
        </span>
        <x-ui.icon name="chevron-right" />
    </a>
</article>
