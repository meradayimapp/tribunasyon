@props(['team', 'size' => 'sm'])
@php($logoUrl = $team->logoUrl())
<span class="team-logo team-logo-{{ $size }} {{ $logoUrl ? 'team-logo-image' : 'team-logo-placeholder' }}" style="--team-primary: {{ $team->primary_color }}" title="{{ $team->name }}">
    @if($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $team->name }} logosu" loading="lazy" decoding="async">
    @else
        {{ $team->short_name }}
    @endif
</span>
