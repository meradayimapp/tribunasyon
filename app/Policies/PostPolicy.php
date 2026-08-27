<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\Team;
use App\Models\User;

class PostPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function create(User $user, Team $team): bool
    {
        return $user->isModerator() && $user->moderatedTeams()->whereKey($team->id)->exists();
    }

    public function update(User $user, Post $post): bool
    {
        return $user->isModerator() && $user->moderatedTeams()->whereKey($post->team_id)->exists();
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }
}
