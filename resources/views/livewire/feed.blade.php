<div>
    <div wire:loading class="loading-indicator mobile-pad mb-2"><span class="spinner-border spinner-border-sm"></span> Akış güncelleniyor…</div>
    @forelse($posts as $post)
        <x-post-card :post="$post" :show-team="$showTeam" wire:key="feed-post-{{ $post->id }}" />
    @empty
        <div class="empty-state"><i class="bi bi-stars"></i><strong>Akışın henüz sakin</strong><p class="mb-0 mt-1">Yeni gönderiler burada görünecek.</p></div>
    @endforelse
    @if($posts->hasPages())<div class="mobile-pad">{{ $posts->links() }}</div>@endif
</div>
