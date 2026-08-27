<?php

namespace App\Livewire;

use App\Models\Team;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class FollowTeam extends Component
{
    public Team $team;

    public function toggle(): mixed
    {
        if (! auth()->check()) {
            return $this->redirectRoute('login', navigate: true);
        }

        abort_unless(auth()->user()->isActive(), 403);
        auth()->user()->followedTeams()->toggle($this->team->id);

        return null;
    }

    public function render(): View
    {
        return view('livewire.follow-team', [
            'following' => auth()->check() && auth()->user()->followedTeams()->whereKey($this->team->id)->exists(),
            'followersCount' => $this->team->followers()->count(),
        ]);
    }
}
