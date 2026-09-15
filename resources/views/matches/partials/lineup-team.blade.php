@php
    $players = data_get($match->lineups, $side.'.starting', []);
    $players = is_array($players) ? array_values(array_filter($players, 'is_array')) : [];
    $subs = data_get($match->lineups, $side.'.subs', []);
    $subs = is_array($subs) ? array_values(array_filter($subs, 'is_array')) : [];
    $formation = data_get($match->lineups, 'formation.'.$side);
    $pitchRows = \App\Services\Football\MatchFormationLayout::rows($formation, $players);
    $coach = data_get($match->lineups, $side.'.coach.name');
@endphp
<div class="match-lineup-team" data-lineup-side="{{ $side }}">
    <div class="match-lineup-team-heading">
        @if($team->logo_url)<img src="{{ $team->logo_url }}" alt="" aria-hidden="true">@endif
        <strong>{{ $team->resolved_name }}</strong>
        @if(filled($formation))<span>{{ $formation }}</span>@endif
    </div>
    @if($pitchRows)
        <div class="match-lineup-pitch" aria-label="{{ $team->resolved_name }} saha dizilişi">
            @foreach($pitchRows as $row)
                <div class="match-lineup-pitch-row">
                    @foreach($row as $player)
                        <div class="match-lineup-pitch-player">
                            <span>{{ $player['number'] ?? '—' }}</span>
                            <strong>{{ $player['name'] ?? 'Oyuncu' }}</strong>
                            @if(isset($player['rating']))<small class="match-player-rating">{{ $player['rating'] }}</small>@endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
    <h4>İlk 11</h4>
    <ol class="match-lineup-list">
        @foreach($players as $player)
            <li>
                <span class="match-lineup-number">{{ $player['number'] ?? '—' }}</span>
                @if(filled($player['image'] ?? null))<img src="{{ $player['image'] }}" alt="" loading="lazy" onerror="this.remove()">@endif
                <span><strong>{{ $player['name'] ?? 'Oyuncu' }}</strong>@if(filled($player['position'] ?? null))<small>{{ $player['position'] }}</small>@endif</span>
                @if(isset($player['rating']))<small class="match-player-rating" aria-label="Oyuncu puanı {{ $player['rating'] }}">{{ $player['rating'] }}</small>@endif
            </li>
        @endforeach
    </ol>
    @if($subs)
        <h4>Yedekler</h4>
        <ol class="match-lineup-list">
            @foreach($subs as $player)
                <li><span class="match-lineup-number">{{ $player['number'] ?? '—' }}</span>
                    @if(filled($player['image'] ?? null))<img src="{{ $player['image'] }}" alt="" loading="lazy" onerror="this.remove()">@endif
                    <span><strong>{{ $player['name'] ?? 'Oyuncu' }}</strong>@if(filled($player['position'] ?? null))<small>{{ $player['position'] }}</small>@endif</span>
                    @if(isset($player['rating']))<small class="match-player-rating">{{ $player['rating'] }}</small>@endif
                </li>
            @endforeach
        </ol>
    @endif
    @if($coach)<p class="match-lineup-coach"><span>Teknik direktör</span><strong>{{ $coach }}</strong></p>@endif
</div>
