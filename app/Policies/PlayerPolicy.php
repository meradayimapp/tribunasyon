<?php

namespace App\Policies;

use App\Models\Player;
use App\Models\User;

class PlayerPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $ability !== 'sendMessage' && $user->isAdmin() ? true : null;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Player $player): bool
    {
        return false;
    }

    public function delete(User $user, Player $player): bool
    {
        return false;
    }

    public function restore(User $user, Player $player): bool
    {
        return false;
    }

    public function sendMessage(User $user, Player $player): bool
    {
        return $user->isActive() && $player->status->value === 'active' && ! $player->trashed();
    }
}
