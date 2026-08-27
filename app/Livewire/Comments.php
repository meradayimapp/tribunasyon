<?php

namespace App\Livewire;

use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class Comments extends Component
{
    public Post $post;

    public string $body = '';

    public ?int $parentId = null;

    public array $expandedReplies = [];

    public function replyTo(int $commentId): void
    {
        $comment = $this->post->comments()->whereNull('parent_id')->findOrFail($commentId);
        $this->parentId = $comment->id;
    }

    public function cancelReply(): void
    {
        $this->parentId = null;
    }

    public function toggleReplies(int $commentId): void
    {
        $comment = $this->post->comments()->whereNull('parent_id')->findOrFail($commentId);

        if (isset($this->expandedReplies[$comment->id])) {
            unset($this->expandedReplies[$comment->id]);

            return;
        }

        $this->expandedReplies[$comment->id] = true;
    }

    public function submit(): mixed
    {
        if (! auth()->check()) {
            return $this->redirectRoute('login', navigate: true);
        }

        abort_unless(auth()->user()->isActive(), 403);
        $this->validate(['body' => ['required', 'string', 'max:1000']]);
        abort_unless(RateLimiter::attempt('comment:'.auth()->id(), 10, fn () => true, 60), 429);

        if ($this->parentId) {
            $parent = $this->post->comments()->whereNull('parent_id')->findOrFail($this->parentId);
            $this->parentId = $parent->id;
        }

        $replyParentId = $this->parentId;
        $this->post->comments()->create(['user_id' => auth()->id(), 'parent_id' => $replyParentId, 'body' => $this->body]);

        if ($replyParentId) {
            $this->expandedReplies[$replyParentId] = true;
        }

        $this->reset('body', 'parentId');
        $this->dispatch('comment-created');

        return null;
    }

    public function toggleLike(int $commentId): mixed
    {
        if (! auth()->check()) {
            return $this->redirectRoute('login', navigate: true);
        }
        abort_unless(auth()->user()->isActive(), 403);
        $comment = $this->post->comments()->findOrFail($commentId);
        $like = $comment->likes()->where('user_id', auth()->id())->first();
        $like ? $like->delete() : $comment->likes()->create(['user_id' => auth()->id()]);

        return null;
    }

    public function render(): View
    {
        $expandedIds = array_map('intval', array_keys(array_filter($this->expandedReplies)));
        $viewerId = auth()->id();
        $query = $this->post->rootComments()->with('user')->withCount(['likes', 'replies'])->latest();

        if ($viewerId) {
            $query->withExists(['likes as liked_by_viewer' => fn ($likes) => $likes->where('user_id', $viewerId)]);
        }

        if ($expandedIds !== []) {
            $query->with(['replies' => function ($replies) use ($expandedIds, $viewerId): void {
                $replies->whereIn('parent_id', $expandedIds)->with('user')->withCount('likes');

                if ($viewerId) {
                    $replies->withExists(['likes as liked_by_viewer' => fn ($likes) => $likes->where('user_id', $viewerId)]);
                }
            }]);
        }

        $comments = $query->get();
        $totalComments = $this->post->comments()->count();

        return view('livewire.comments', compact('comments', 'totalComments'));
    }
}
