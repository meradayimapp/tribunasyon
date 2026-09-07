<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Services\CommunitySearchService;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, CommunitySearchService $search): View
    {
        $query = (string) $request->validated('q', '');

        return view('search.index', [
            'query' => $query,
            'teams' => $query === '' ? collect() : $search->teams($query, 20),
            'posts' => $query === '' ? collect() : $search->posts($query, 30),
            'players' => mb_strlen($query) < 2 ? collect() : $search->players($query, 20),
        ]);
    }
}
