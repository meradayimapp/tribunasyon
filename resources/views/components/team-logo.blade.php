@props(['team', 'size' => 'sm'])
<span class="team-logo team-logo-{{ $size }}" style="--team-primary: {{ $team->primary_color }}" title="{{ $team->name }}">
    @if($team->logo)
        <img src="{{ Storage::url($team->logo) }}" alt="{{ $team->name }} logosu">
    @else
        {{ $team->short_name }}
    @endif
</span>
