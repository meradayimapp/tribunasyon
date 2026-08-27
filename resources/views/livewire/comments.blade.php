<section id="yorumlar" class="comments-section">
    <div class="comments-heading">
        <h2>Yorumlar</h2>
        <span>{{ number_format($totalComments, 0, ',', '.') }}</span>
    </div>

    <form wire:submit="submit" class="comment-composer">
        @if($parentId)
            <div class="reply-banner">
                <span><i class="bi bi-reply"></i> Bir yoruma yanıt veriyorsun</span>
                <button type="button" class="btn-close" wire:click="cancelReply" aria-label="Yanıtı iptal et"></button>
            </div>
        @endif
        <div class="composer-row">
            @auth<x-avatar :user="auth()->user()" size="sm" />@endauth
            <textarea wire:model="body" class="form-control @error('body') is-invalid @enderror" rows="1" maxlength="1000" placeholder="Yorum ekle…" aria-label="Yorum metni"></textarea>
            <button class="comment-submit" wire:loading.attr="disabled" wire:target="submit" aria-label="Yorumu gönder">
                <span wire:loading.remove wire:target="submit">Gönder</span>
                <span class="mini-loader" wire:loading wire:target="submit"></span>
            </button>
        </div>
        @error('body')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </form>

    <div class="comments-list">
        @forelse($comments as $comment)
            @php($repliesOpen = isset($expandedReplies[$comment->id]))
            <article class="comment-thread" wire:key="comment-{{ $comment->id }}">
                <div class="comment">
                    <x-avatar :user="$comment->user" size="sm" />
                    <div class="comment-content">
                        <a class="comment-username" href="{{ route('profile.show', $comment->user) }}">{{ '@'.$comment->user->username }}</a>
                        <p class="comment-text">{{ $comment->body }}</p>
                        <div class="comment-actions">
                            <time datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->diffForHumans() }}</time>
                            <button class="comment-action {{ ($comment->liked_by_viewer ?? false) ? 'liked' : '' }}" type="button" wire:click="toggleLike({{ $comment->id }})" wire:loading.attr="disabled" aria-label="Yorumu beğen">
                                Beğen
                                @if($comment->likes_count)<span>{{ $comment->likes_count }}</span>@endif
                            </button>
                            <button class="comment-action" type="button" wire:click="replyTo({{ $comment->id }})">Yanıtla</button>
                        </div>

                        @if($comment->replies_count > 0)
                            <button class="replies-toggle" type="button" wire:click="toggleReplies({{ $comment->id }})" wire:loading.attr="disabled" wire:target="toggleReplies({{ $comment->id }})" aria-expanded="{{ $repliesOpen ? 'true' : 'false' }}">
                                <span class="reply-line"></span>
                                <span wire:loading.remove wire:target="toggleReplies({{ $comment->id }})">{{ $repliesOpen ? 'Yanıtları gizle' : $comment->replies_count.' yanıtı gör' }}</span>
                                <span wire:loading wire:target="toggleReplies({{ $comment->id }})"><span class="mini-loader"></span></span>
                            </button>
                        @endif
                    </div>
                </div>

                @if($repliesOpen)
                    <div class="replies" wire:key="replies-{{ $comment->id }}">
                        @foreach($comment->replies as $reply)
                            <article class="comment reply" wire:key="reply-{{ $reply->id }}">
                                <x-avatar :user="$reply->user" size="sm" />
                                <div class="comment-content">
                                    <a class="comment-username" href="{{ route('profile.show', $reply->user) }}">{{ '@'.$reply->user->username }}</a>
                                    <p class="comment-text">{{ $reply->body }}</p>
                                    <div class="comment-actions">
                                        <time datetime="{{ $reply->created_at->toIso8601String() }}">{{ $reply->created_at->diffForHumans() }}</time>
                                        <button class="comment-action {{ ($reply->liked_by_viewer ?? false) ? 'liked' : '' }}" type="button" wire:click="toggleLike({{ $reply->id }})" wire:loading.attr="disabled">
                                            Beğen
                                            @if($reply->likes_count)<span>{{ $reply->likes_count }}</span>@endif
                                        </button>
                                        <button class="comment-action" type="button" wire:click="replyTo({{ $comment->id }})">Yanıtla</button>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </article>
        @empty
            <div class="comments-empty"><i class="bi bi-chat"></i><strong>İlk yorumu sen yaz.</strong><span>Sohbeti başlat ve takımına ses ver.</span></div>
        @endforelse
    </div>
</section>
