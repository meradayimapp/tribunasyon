<?php

namespace App\Livewire;

use App\Enums\PlayerStatus;
use App\Models\Player;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PlayerFollowButton extends Component
{
    public Player $player;

    public bool $following = false;

    public int $followersCount = 0;

    public function mount(): void
    {
        $this->followersCount = isset($this->player->followers_count)
            ? (int) $this->player->followers_count
            : $this->player->followers()->count();
        $this->following = auth()->check()
            && auth()->user()->followedPlayers()->whereKey($this->player->id)->exists();
    }

    public function toggle(): mixed
    {
        if (! auth()->check()) {
            return $this->redirectRoute('login', navigate: true);
        }

        abort_unless(auth()->user()->isActive(), 403);
        abort_unless($this->player->status === PlayerStatus::Active && ! $this->player->trashed(), 404);

        if ($this->following) {
            auth()->user()->followedPlayers()->detach($this->player->id);
            $this->following = false;
            $this->followersCount = max(0, $this->followersCount - 1);
        } else {
            auth()->user()->followedPlayers()->syncWithoutDetaching([$this->player->id]);
            $this->following = true;
            $this->followersCount++;
        }

        return null;
    }

    public function render(): View
    {
        return view('livewire.player-follow-button');
    }
}
