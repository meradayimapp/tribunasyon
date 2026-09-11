@extends('layouts.app')
@section('title', $player->name)
@section('mobile-title', $player->name)
@section('mobile-back', route('players.index'))
@section('content')
<div class="player-profile mx-auto" x-data="{ tab: 'general' }">
    <section class="player-hero">
        @if($player->coverImageUrl())<img class="player-cover" src="{{ $player->coverImageUrl() }}" alt="" loading="eager" decoding="async">@endif
        <div class="player-hero-shade"></div>
        <div class="player-hero-content">
            <div class="player-profile-photo">
                @if($player->photoUrl())<img src="{{ $player->photoUrl() }}" alt="{{ $player->name }} fotoğrafı" loading="eager" decoding="async">@else<span>{{ mb_strtoupper(mb_substr($player->name, 0, 2)) }}</span>@endif
            </div>
            <div class="player-profile-copy">
                <div class="eyebrow">Oyuncu profili</div>
                <h1>{{ $player->name }}</h1>
                @if($engagementBadges)
                    <div class="player-engagement-badges">
                        @foreach($engagementBadges as $badge)<span><x-ui.icon name="trophy" />{{ $badge }}</span>@endforeach
                    </div>
                @endif
                <div class="player-identity-line">
                    @if($player->position)<span>{{ $player->position }}</span>@endif
                    @if($player->currentTeam)<span><x-team-logo :team="$player->currentTeam" size="xs" />{{ $player->currentTeam->name }}</span>@else<span>Serbest oyuncu</span>@endif
                    @if($player->nationality)<span>{{ $player->nationality }}</span>@endif
                    @if($player->national_team_name)<span>{{ $player->national_team_name }}</span>@endif
                </div>
            </div>
            <livewire:player-follow-button :player="$player" />
        </div>
    </section>

    <div class="player-tabs" role="tablist" aria-label="Oyuncu profil bölümleri">
        <button type="button" role="tab" :aria-selected="(tab === 'general').toString()" :class="{ active: tab === 'general' }" @click="tab = 'general'">Genel</button>
        <button type="button" role="tab" :aria-selected="(tab === 'chat').toString()" :class="{ active: tab === 'chat' }" @click="tab = 'chat'">Canlı Sohbet</button>
    </div>

    <section x-show="tab === 'general'" class="player-general" role="tabpanel">
        @if($player->bio)<div class="player-bio"><h2>{{ $player->name }}</h2><p>{{ $player->bio }}</p></div>@endif
        <div class="player-facts">
            @if($player->currentTeam)<div><span>Takım</span><strong>{{ $player->currentTeam->name }}</strong></div>@endif
            @if($player->position)<div><span>Pozisyon</span><strong>{{ $player->position }}</strong></div>@endif
            @if($player->shirt_number !== null)<div><span>Forma</span><strong>#{{ $player->shirt_number }}</strong></div>@endif
            @if($player->nationality)<div><span>Ülke / uyruk</span><strong>{{ $player->nationality }}</strong></div>@endif
            @if($player->national_team_name)<div><span>Milli takım</span><strong>{{ $player->national_team_name }}</strong></div>@endif
            @if($player->birth_date)<div><span>Yaş</span><strong>{{ $player->age }}</strong><small>{{ $player->birth_date->format('d.m.Y') }}</small></div>@endif
            @if($player->formatted_market_value)<div><span>Piyasa değeri</span><strong>{{ $player->formatted_market_value }}</strong></div>@endif
        </div>
    </section>

    <div x-cloak x-show="tab === 'chat'" role="tabpanel"><livewire:player-chat :player="$player" /></div>
</div>
@endsection
