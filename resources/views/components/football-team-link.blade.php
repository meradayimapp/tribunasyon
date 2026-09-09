@props(['team', 'away' => false])
@php($logo = $team->logoUrl())
<a {{ $attributes->class(['football-team-link', 'match-team-identity-away' => $away]) }} href="{{ $team->publicUrl() }}">
    @if(! $away)
        @if($logo)<img class="match-team-logo" src="{{ $logo }}" alt="" loading="lazy" decoding="async">@else<span class="match-team-logo match-team-logo-fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr($team->resolved_name, 0, 1)) }}</span>@endif
    @endif
    <strong>{{ $team->resolved_name }}</strong>
    @if($away)
        @if($logo)<img class="match-team-logo" src="{{ $logo }}" alt="" loading="lazy" decoding="async">@else<span class="match-team-logo match-team-logo-fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr($team->resolved_name, 0, 1)) }}</span>@endif
    @endif
</a>
