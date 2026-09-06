<div class="post-engagement" x-data="{ shared: false, async share() { const url = @js(route('posts.show', [$post->team, $post])); try { if (navigator.share) { await navigator.share({ title: @js($post->team->name), url }); } else { await navigator.clipboard.writeText(url); this.shared = true; setTimeout(() => this.shared = false, 1800); } } catch (error) { if (error.name !== 'AbortError') this.shared = false; } } }">
    <div class="post-toolbar">
        <button type="button" class="icon-action {{ $liked ? 'liked' : '' }}" wire:click="toggleLike" wire:loading.attr="disabled" wire:target="toggleLike" aria-label="{{ $liked ? 'Beğeniyi kaldır' : 'Gönderiyi beğen' }}" aria-pressed="{{ $liked ? 'true' : 'false' }}">
            <x-ui.icon :name="$liked ? 'heart-filled' : 'heart'" />
            <span class="mini-loader" wire:loading wire:target="toggleLike"></span>
        </button>
        <a class="icon-action" href="{{ route('posts.show', [$post->team, $post]) }}#yorumlar" aria-label="{{ number_format($commentsCount, 0, ',', '.') }} yorumu aç"><x-ui.icon name="comment" /></a>
        <button type="button" class="icon-action" @click="share" aria-label="Gönderiyi paylaş"><x-ui.icon name="send" /></button>
        <span class="share-feedback" x-show="shared" x-transition.opacity x-cloak>Bağlantı kopyalandı</span>
    </div>
    <div class="likes-summary">{{ number_format($likesCount, 0, ',', '.') }} beğeni</div>
</div>
