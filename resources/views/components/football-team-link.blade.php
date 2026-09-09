@props(['team', 'away' => false])
<a {{ $attributes->class(['football-team-link', 'match-team-identity-away' => $away]) }} href="{{ $team->publicUrl() }}">
    @if(! $away && ($logo = $team->logoUrl()))
        <img class="match-team-logo" src="{{ $logo }}" alt="" loading="lazy" decoding="async">
    @endif
    <strong>{{ $team->resolved_name }}</strong>
    @if($away && ($logo = $team->logoUrl()))
        <img class="match-team-logo" src="{{ $logo }}" alt="" loading="lazy" decoding="async">
    @endif
</a>
