@props(['player'])
@php
    $name = trim((string) ($player['name'] ?? 'Oyuncu')) ?: 'Oyuncu';
    $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = collect($words)
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1), 'UTF-8'))
        ->implode('') ?: 'OY';
    $profileUrl = filled($player['profile_url'] ?? null) ? $player['profile_url'] : null;
    $imageUrl = filled($player['image'] ?? null) ? $player['image'] : null;
    $position = filled($player['position_display'] ?? null) ? $player['position_display'] : null;
    $number = filled($player['number'] ?? null) ? $player['number'] : '—';
@endphp

@if($profileUrl)
    <a class="match-lineup-pitch-player match-lineup-player-link" href="{{ $profileUrl }}" aria-label="{{ $name }} profiline git">
@else
    <div class="match-lineup-pitch-player">
@endif
    <span class="match-lineup-pitch-avatar">
        @if($imageUrl)
            <img src="{{ $imageUrl }}" alt="" loading="lazy" decoding="async" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
            <span class="match-lineup-pitch-avatar-fallback" hidden aria-hidden="true">{{ $initials }}</span>
        @else
            <span class="match-lineup-pitch-avatar-fallback" aria-hidden="true">{{ $initials }}</span>
        @endif
        <b class="match-lineup-pitch-number">{{ $number }}</b>
    </span>
    <span class="match-lineup-pitch-label">
        <strong title="{{ $name }}">{{ $name }}</strong>
        @if($position)<small>{{ $position }}</small>@endif
    </span>
    @if(isset($player['rating']))<small class="match-player-rating" aria-label="Oyuncu puanı {{ $player['rating'] }}">{{ $player['rating'] }}</small>@endif
@if($profileUrl)
    </a>
@else
    </div>
@endif
