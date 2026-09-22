@props(['match', 'turkeyProviderTeamId' => null])
@php
    $scoreKnown = $match->home_score !== null || $match->away_score !== null;
    $initialPrimary = $scoreKnown ? ($match->home_score ?? '–').' - '.($match->away_score ?? '–') : $match->kickoffTime();
    $initialMinute = $match->isHalfTime() ? 'DEVRE' : ($match->is_live && $match->displayMinute() !== null ? $match->displayMinute().'′' : null);
    $initialStatus = $match->is_live
        ? ($match->isHalfTime() ? null : 'CANLI')
        : ($match->isCompleted() ? 'Bitti' : $match->statusLabel());
    $directGroup = data_get($match->meta, 'group') ?? data_get($match->meta, 'league_group');
    $stageParts = collect([$match->round, is_scalar($directGroup) ? trim((string) $directGroup) : null])->filter()->unique()->values();
    $isTurkeyMatch = $turkeyProviderTeamId !== null && in_array($turkeyProviderTeamId, [
        $match->homeTeam->provider_team_id,
        $match->awayTeam->provider_team_id,
    ], true);
@endphp
<article data-match-state data-live-match-id="{{ $match->id }}" @class(['competition-match-row', 'is-live' => $match->is_live, 'is-turkey' => $isTurkeyMatch])>
    <a href="{{ route('matches.show', $match) }}" aria-label="{{ $match->homeTeam->resolved_name }} - {{ $match->awayTeam->resolved_name }} maç merkezini aç">
        @if($stageParts->isNotEmpty())
            <span class="competition-match-stage">{{ $stageParts->implode(' • ') }}</span>
        @endif
        <span class="competition-matchup">
            <span class="competition-match-team competition-match-team-home">
                <span class="competition-match-logo">@if($logo = $match->homeTeam->logoUrl())<img src="{{ $logo }}" alt="" loading="lazy" decoding="async">@else{{ mb_strtoupper(mb_substr($match->homeTeam->resolved_name, 0, 2)) }}@endif</span>
                <strong>{{ $match->homeTeam->resolved_name }}</strong>
            </span>
            <span class="competition-match-center">
                <small class="competition-match-minute" x-show="matchMinute({{ $match->id }}, @js($initialMinute)) !== null" x-text="matchMinute({{ $match->id }}, @js($initialMinute))" @if($initialMinute === null) x-cloak @endif>{{ $initialMinute }}</small>
                <strong x-text="matchPrimary({{ $match->id }}, @js($initialPrimary))">{{ $initialPrimary }}</strong>
                <small class="competition-match-status" :class="{ 'is-live': isLive({{ $match->id }}, {{ $match->is_live ? 'true' : 'false' }}) }" x-show="matchStatus({{ $match->id }}, @js($initialStatus)) !== null" x-text="matchStatus({{ $match->id }}, @js($initialStatus))" @if($initialStatus === null) x-cloak @endif>{{ $initialStatus }}</small>
            </span>
            <span class="competition-match-team competition-match-team-away">
                <strong>{{ $match->awayTeam->resolved_name }}</strong>
                <span class="competition-match-logo">@if($logo = $match->awayTeam->logoUrl())<img src="{{ $logo }}" alt="" loading="lazy" decoding="async">@else{{ mb_strtoupper(mb_substr($match->awayTeam->resolved_name, 0, 2)) }}@endif</span>
            </span>
            <x-ui.icon name="chevron-right" />
        </span>
    </a>
</article>
