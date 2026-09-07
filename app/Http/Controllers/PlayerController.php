<?php

namespace App\Http\Controllers;

use App\Enums\PlayerStatus;
use App\Models\Player;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function index(): View
    {
        return view('players.index');
    }

    public function show(Player $player): View
    {
        abort_unless($player->status === PlayerStatus::Active, 404);
        $player->load('currentTeam.organization')->loadCount('followers');

        return view('players.show', compact('player'));
    }
}
