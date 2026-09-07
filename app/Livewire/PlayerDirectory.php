<?php

namespace App\Livewire;

use App\Models\Player;
use App\Services\PlayerEngagementService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PlayerDirectory extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'sirala')]
    public string $sort = PlayerEngagementService::WEEKLY;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSort(string $sort): void
    {
        if (! in_array($sort, PlayerEngagementService::SORTS, true)) {
            $this->sort = PlayerEngagementService::WEEKLY;
        }

        $this->resetPage();
    }

    public function render(PlayerEngagementService $engagement): View
    {
        $term = trim($this->search);
        $query = Player::query()->active()->with('currentTeam');

        if ($term !== '') {
            mb_strlen($term) >= 2 ? $query->matching($term) : $query->whereRaw('1 = 0');
        }

        $sort = in_array($this->sort, PlayerEngagementService::SORTS, true)
            ? $this->sort
            : PlayerEngagementService::WEEKLY;

        return view('livewire.player-directory', [
            'players' => $engagement->applyRanking($query, $sort)->paginate(24),
            'rankingActive' => $sort !== PlayerEngagementService::MANUAL,
        ]);
    }
}
