<section id="yorumlar">
    <h2 class="h5 fw-bold">Yorumlar <span class="muted">{{ $comments->sum(fn($c) => 1 + $c->replies->count()) }}</span></h2>
    <form wire:submit="submit" class="my-3">
        @if($parentId)<div class="reply-banner"><span>Bir yoruma yanıt veriyorsun</span><button type="button" class="btn-close" wire:click="cancelReply" aria-label="Yanıtı iptal et"></button></div>@endif
        <div class="d-flex gap-2"><textarea wire:model="body" class="form-control @error('body') is-invalid @enderror" rows="2" maxlength="1000" placeholder="Sohbete katıl…"></textarea><button class="btn btn-primary align-self-stretch" wire:loading.attr="disabled">Gönder</button></div>
        @error('body')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </form>
    @forelse($comments as $comment)
        <article class="comment" wire:key="comment-{{ $comment->id }}"><x-avatar :user="$comment->user" size="sm" /><div class="comment-content"><div class="comment-meta"><a class="comment-username" href="{{ route('profile.show',$comment->user) }}">{{ '@'.$comment->user->username }}</a><span>{{ $comment->created_at->diffForHumans() }}</span></div><p class="comment-text">{{ $comment->body }}</p><div class="d-flex gap-3"><button class="action-btn p-0 min-h-0" wire:click="toggleLike({{ $comment->id }})"><i class="bi bi-heart"></i> {{ $comment->likes_count }}</button><button class="action-btn p-0 min-h-0" wire:click="replyTo({{ $comment->id }})">Yanıtla</button></div></div></article>
        @if($comment->replies->isNotEmpty())<div class="replies">@foreach($comment->replies as $reply)<article class="comment" wire:key="reply-{{ $reply->id }}"><x-avatar :user="$reply->user" size="sm" /><div class="comment-content"><div class="comment-meta"><a class="comment-username" href="{{ route('profile.show',$reply->user) }}">{{ '@'.$reply->user->username }}</a><span>{{ $reply->created_at->diffForHumans() }}</span></div><p class="comment-text">{{ $reply->body }}</p><button class="action-btn p-0 min-h-0" wire:click="toggleLike({{ $reply->id }})"><i class="bi bi-heart"></i> {{ $reply->likes_count }}</button></div></article>@endforeach</div>@endif
    @empty<div class="empty-state py-4"><i class="bi bi-chat"></i>İlk yorumu sen yaz.</div>@endforelse
</section>
