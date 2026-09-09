<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\FootballMatchChatMessage;
use App\Models\User;

class FootballMatchChatMessagePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function delete(User $user, FootballMatchChatMessage $message): bool
    {
        if ($message->user->isAdmin()) {
            return false;
        }

        if ($user->id === $message->user_id) {
            return true;
        }

        if (! $user->isModerator() || $message->user->role !== UserRole::Member) {
            return false;
        }

        $message->footballMatch->loadMissing(['homeTeam:id,team_id', 'awayTeam:id,team_id']);
        $teamIds = array_filter([
            $message->footballMatch->homeTeam?->team_id,
            $message->footballMatch->awayTeam?->team_id,
        ]);

        return $teamIds !== [] && $user->moderatedTeams()->whereKey($teamIds)->exists();
    }
}
