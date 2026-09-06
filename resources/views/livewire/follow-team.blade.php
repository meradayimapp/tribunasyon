<div class="follow-wrap">
    <span class="follow-count"><strong>{{ number_format($followersCount, 0, ',', '.') }}</strong> takipçi</span>
    <button type="button" wire:click="toggle" wire:loading.attr="disabled" wire:target="toggle" class="follow-button {{ $following ? 'following' : '' }}" aria-pressed="{{ $following ? 'true' : 'false' }}">
        <span wire:loading.remove wire:target="toggle"><x-ui.icon :name="$following ? 'check' : 'add'" />{{ $following ? 'Takiptesin' : 'Takip et' }}</span>
        <span wire:loading wire:target="toggle"><span class="mini-loader"></span></span>
    </button>
</div>
