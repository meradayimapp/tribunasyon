<?php

namespace App\Livewire;

use App\Services\CommunitySearchService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class HeaderSearch extends Component
{
    public string $query = '';

    public function updatedQuery(): void
    {
        $this->validateOnly('query');
    }

    public function submit()
    {
        $this->validate();

        return $this->redirectRoute('search.index', ['q' => trim($this->query)]);
    }

    protected function rules(): array
    {
        return ['query' => ['nullable', 'string', 'max:100']];
    }

    public function render(CommunitySearchService $search): View
    {
        $normalized = trim($this->query);
        $searchable = mb_strlen($normalized) >= 2 && mb_strlen($normalized) <= 100;

        return view('livewire.header-search', [
            'teams' => $searchable ? $search->teams($normalized, 4) : collect(),
            'posts' => $searchable ? $search->posts($normalized, 4) : collect(),
            'showSuggestions' => $searchable,
        ]);
    }
}
