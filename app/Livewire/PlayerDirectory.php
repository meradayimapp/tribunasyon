<?php

namespace App\Livewire;

use App\Models\Player;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PlayerDirectory extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $term = trim($this->search);
        $query = Player::query()->active()->with('currentTeam');

        if ($term !== '') {
            mb_strlen($term) >= 2 ? $query->matching($term) : $query->whereRaw('1 = 0');
        }

        return view('livewire.player-directory', [
            'players' => $query->ordered()->paginate(24),
        ]);
    }
}
