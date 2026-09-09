<?php

namespace App\Policies;

use App\Models\FootballMatch;
use App\Models\User;

class FootballMatchPolicy
{
    public function sendMessage(User $user, FootballMatch $footballMatch): bool
    {
        return $user->isActive() && $footballMatch->exists;
    }
}
