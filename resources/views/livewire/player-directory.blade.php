<div>
    <label class="player-search" aria-label="Oyuncu ara">
        <x-ui.icon name="search" />
        <input type="search" wire:model.live.debounce.350ms="search" placeholder="Oyuncu ara..." maxlength="100">
        <span wire:loading wire:target="search" class="mini-loader"></span>
    </label>
    @if(mb_strlen(trim($search)) === 1)<p class="player-search-help">Aramak için en az 2 karakter yaz.</p>@endif

    <div class="player-grid" wire:loading.class="is-loading" wire:target="search,gotoPage,previousPage,nextPage">
        @forelse($players as $player)
            <a class="player-card" href="{{ route('players.show', $player) }}" wire:key="player-card-{{ $player->id }}">
                <div class="player-card-photo">
                    @if($player->photoUrl())<img src="{{ $player->photoUrl() }}" alt="{{ $player->name }} fotoğrafı" loading="lazy" decoding="async">@else<span>{{ mb_strtoupper(mb_substr($player->name, 0, 2)) }}</span>@endif
                </div>
                <div class="player-card-copy">
                    <h2>{{ $player->name }}</h2>
                    <p>{{ $player->position ?: 'Futbolcu' }}</p>
                    @if($player->currentTeam)<div class="player-card-team"><x-team-logo :team="$player->currentTeam" size="xs" /><span>{{ $player->currentTeam->name }}</span></div>@endif
                    <div class="player-card-meta">
                        @if($player->national_team_name)<span>{{ $player->national_team_name }}</span>@endif
                        @if($player->formatted_market_value)<strong>{{ $player->formatted_market_value }}</strong>@endif
                    </div>
                </div>
            </a>
        @empty
            <div class="empty-state player-empty"><x-ui.icon name="person" /><strong>Oyuncu bulunamadı</strong><p>Başka bir isim veya takım deneyin.</p></div>
        @endforelse
    </div>
    @if($players->hasPages())<div class="feed-pagination">{{ $players->links('pagination::simple-bootstrap-5') }}</div>@endif
</div>
