<?php

namespace App\Livewire;

use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\On;
use Livewire\Component;

class PostActions extends Component
{
    public Post $post;

    public bool $liked = false;

    public int $likesCount = 0;

    public int $commentsCount = 0;

    public function mount(): void
    {
        $this->liked = auth()->check()
            ? (bool) ($this->post->liked_by_viewer ?? $this->post->likes()->where('user_id', auth()->id())->exists())
            : false;
        $this->likesCount = (int) ($this->post->likes_count ?? $this->post->likes()->count());
        $this->commentsCount = (int) ($this->post->comments_count ?? $this->post->comments()->count());
    }

    public function toggleLike(): mixed
    {
        if (! auth()->check()) {
            return $this->redirectRoute('login', navigate: true);
        }

        abort_unless(auth()->user()->isActive(), 403);
        abort_unless(RateLimiter::attempt('post-like:'.auth()->id(), 30, fn () => true, 60), 429);
        $like = $this->post->likes()->where('user_id', auth()->id())->first();
        if ($like) {
            $like->delete();
            $this->liked = false;
            $this->likesCount--;
        } else {
            $this->post->likes()->create(['user_id' => auth()->id()]);
            $this->liked = true;
            $this->likesCount++;
        }

        return null;
    }

    #[On('comment-created')]
    public function refreshComments(): void
    {
        $this->commentsCount = $this->post->comments()->count();
    }

    public function render(): View
    {
        return view('livewire.post-actions');
    }
}
