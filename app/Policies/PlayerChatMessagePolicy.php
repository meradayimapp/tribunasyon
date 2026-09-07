<?php

namespace App\Policies;

use App\Models\PlayerChatMessage;
use App\Models\User;

class PlayerChatMessagePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function delete(User $user, PlayerChatMessage $message): bool
    {
        if ($user->id === $message->user_id) {
            return true;
        }

        return $user->isModerator()
            && $message->player->current_team_id !== null
            && $user->moderatedTeams()->whereKey($message->player->current_team_id)->exists();
    }
}
