<div>
    <div wire:loading class="loading-indicator"><span class="mini-loader"></span> Akış güncelleniyor…</div>
    @forelse($posts as $post)
        <x-post-card :post="$post" :show-team="$showTeam" wire:key="feed-post-{{ $post->id }}" />
    @empty
        <div class="empty-state"><x-ui.icon name="sparkle" /><strong>Akışın henüz sakin</strong><p>Yeni gönderiler burada görünecek.</p></div>
    @endforelse
    @if($posts->hasPages())<div class="feed-pagination">{{ $posts->links('pagination::simple-bootstrap-5') }}</div>@endif
</div>
