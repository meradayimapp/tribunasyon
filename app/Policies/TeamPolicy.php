<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function manage(User $user, Team $team): bool
    {
        return $user->isModerator() && $user->moderatedTeams()->whereKey($team->id)->exists();
    }
}
