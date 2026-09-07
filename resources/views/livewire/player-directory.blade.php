<div>
    <div class="player-directory-tools">
        <label class="player-search" aria-label="Oyuncu ara">
            <x-ui.icon name="search" />
            <input type="search" wire:model.live.debounce.350ms="search" placeholder="Oyuncu ara..." maxlength="100">
            <span wire:loading wire:target="search" class="mini-loader"></span>
        </label>
        <div class="player-ranking-tabs" role="group" aria-label="Oyuncu sıralaması">
            <button type="button" wire:click="$set('sort', 'weekly')" @class(['active' => $sort === 'weekly'])>Bu hafta</button>
            <button type="button" wire:click="$set('sort', 'monthly')" @class(['active' => $sort === 'monthly'])>Bu ay</button>
            <button type="button" wire:click="$set('sort', 'manual')" @class(['active' => $sort === 'manual'])>Önerilen</button>
        </div>
    </div>
    @if(mb_strlen(trim($search)) === 1)<p class="player-search-help">Aramak için en az 2 karakter yaz.</p>@endif

    <div class="player-grid" wire:loading.class="is-loading" wire:target="search,sort,gotoPage,previousPage,nextPage">
        @forelse($players as $player)
            @php($periodInteractions = (int) ($player->period_messages_count ?? 0) + (int) ($player->period_follows_count ?? 0))
            <a class="player-card" href="{{ route('players.show', $player) }}" wire:key="player-card-{{ $player->id }}">
                <div class="player-card-photo">
                    @if($player->photoUrl())<img src="{{ $player->photoUrl() }}" alt="{{ $player->name }} fotoğrafı" loading="lazy" decoding="async">@else<span>{{ mb_strtoupper(mb_substr($player->name, 0, 2)) }}</span>@endif
                    @if($rankingActive && $periodInteractions > 0 && ($players->firstItem() + $loop->index) <= 3)
                        <span class="player-rank-badge"><x-ui.icon name="trophy" /> #{{ $players->firstItem() + $loop->index }}</span>
                    @endif
                </div>
                <div class="player-card-copy">
                    <h2>{{ $player->name }}</h2>
                    <p>{{ $player->position ?: 'Futbolcu' }}</p>
                    @if($player->currentTeam)<div class="player-card-team"><x-team-logo :team="$player->currentTeam" size="xs" /><span>{{ $player->currentTeam->name }}</span></div>@endif
                    <div class="player-card-meta">
                        @if($rankingActive)
                            <span class="player-card-activity"><x-ui.icon name="comment" /> {{ number_format($player->period_messages_count ?? 0, 0, ',', '.') }} mesaj@if(($player->period_follows_count ?? 0) > 0) · {{ number_format($player->period_follows_count, 0, ',', '.') }} takip@endif</span>
                        @elseif($player->national_team_name)<span>{{ $player->national_team_name }}</span>@endif
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
