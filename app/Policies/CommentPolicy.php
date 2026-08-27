<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $user->isModerator()
            && $user->moderatedTeams()->whereKey($comment->post->team_id)->exists();
    }
}
