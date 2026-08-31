<?php

namespace App\Livewire;

use App\Models\Post;
use App\Models\Team;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Feed extends Component
{
    use WithPagination;

    public ?Team $team = null;

    public bool $showTeam = true;

    public function render(): View
    {
        $query = Post::query()->published()->with(['team', 'media'])->withCount(['likes', 'comments']);

        if ($this->team) {
            $query->whereBelongsTo($this->team);
        } else {
            $query->whereHas('team', fn ($teams) => $teams->active());

            if (auth()->check() && auth()->user()->followedTeams()->exists()) {
                $query->whereIn('team_id', auth()->user()->followedTeams()->select('teams.id'));
            }
        }

        if (auth()->check()) {
            $query->withExists(['likes as liked_by_viewer' => fn ($likes) => $likes->where('user_id', auth()->id())]);
        }

        return view('livewire.feed', [
            'posts' => $query
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(8),
        ]);
    }
}
