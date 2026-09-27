@props(['matches'])
@php
    $competition = $matches->first()->competition;
    $competitionName = $competition->display_name ?: $competition->name;
    $groupValues = $matches->map(function ($match) {
        $value = data_get($match->meta, 'group') ?? data_get($match->meta, 'league_group');

        return is_scalar($value) ? trim((string) $value) : null;
    })->filter()->unique()->values();
    $groupLabel = $groupValues->count() === 1 ? $groupValues->first() : null;
@endphp
<section class="matches-competition-group" aria-labelledby="matches-competition-{{ $competition->id }}">
    <a class="matches-competition-heading" href="{{ route('competitions.show', $competition->slug) }}">
        <span class="matches-competition-logo">
            @if($logo = $competition->logoUrl())
                <img src="{{ $logo }}" alt="" loading="lazy" decoding="async">
            @else
                <x-ui.icon name="trophy" />
            @endif
        </span>
        <span class="matches-competition-copy">
            <small>{{ $competition->regionLabel() }}</small>
            <strong id="matches-competition-{{ $competition->id }}">{{ $competitionName }}</strong>
            @if($groupLabel)<small>{{ $groupLabel }}</small>@endif
        </span>
        <x-ui.icon name="chevron-right" />
    </a>
    <div class="matches-list">
        @foreach($matches as $match)
            <x-football.match-list-row :match="$match" />
        @endforeach
    </div>
</section>
