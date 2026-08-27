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

    public function replyTo(int $commentId): void
    {
        $comment = $this->post->comments()->whereNull('parent_id')->findOrFail($commentId);
        $this->parentId = $comment->id;
    }

    public function cancelReply(): void
    {
        $this->parentId = null;
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

        $this->post->comments()->create(['user_id' => auth()->id(), 'parent_id' => $this->parentId, 'body' => $this->body]);
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
        $comments = $this->post->rootComments()->with(['user', 'replies.user'])->withCount('likes')
            ->with(['replies' => fn ($query) => $query->withCount('likes')])->latest()->get();

        return view('livewire.comments', compact('comments'));
    }
}
