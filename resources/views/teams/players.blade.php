@extends('layouts.app')
@section('title', $team->name.' Oyuncuları')
@section('mobile-title', $team->name)
@section('mobile-back', route('teams.index'))
@section('content')
<div class="feed-column public-feed mx-auto">
    @include('teams.partials.profile-header', ['activeTab' => 'players'])

    <div class="team-tab-content team-players-content">
        <form class="team-player-search" method="GET" action="{{ route('teams.players', $team) }}" role="search">
            <x-ui.icon name="search" />
            <input type="search" name="q" value="{{ $search }}" placeholder="Oyuncu ara..." aria-label="Oyuncu ara" maxlength="100" autocomplete="off">
            @if($search !== '')<a href="{{ route('teams.players', $team) }}" aria-label="Aramayı temizle"><x-ui.icon name="close" /></a>@endif
        </form>

        <div class="team-player-list">
            @forelse($players as $player)
                <a class="team-player-row" href="{{ route('players.show', $player) }}">
                    @if($player->shirt_number !== null)<span class="team-player-number">{{ $player->shirt_number }}</span>@else<span class="team-player-number" aria-hidden="true">—</span>@endif
                    <span class="team-player-avatar">
                        @if($player->photoUrl())<img src="{{ $player->photoUrl() }}" alt="" loading="lazy" decoding="async">@else<span>{{ mb_strtoupper(mb_substr($player->name, 0, 2)) }}</span>@endif
                    </span>
                    <span class="team-player-copy"><strong>{{ $player->name }}</strong>@if($player->position)<small>{{ $player->position }}</small>@endif</span>
                    <x-ui.icon name="chevron-right" />
                </a>
            @empty
                <div class="empty-state team-tab-empty"><x-ui.icon name="person" /><strong>{{ $search === '' ? 'Henüz oyuncu yok' : 'Oyuncu bulunamadı' }}</strong><p>{{ $search === '' ? 'Bu takımda henüz oyuncu bulunmuyor.' : 'Aramanızla eşleşen oyuncu bulunamadı.' }}</p></div>
            @endforelse
        </div>
    </div>
</div>
@endsection
