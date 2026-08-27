<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Team;
use Illuminate\View\View;

class PostController extends Controller
{
    public function show(Team $team, Post $post): View
    {
        abort_unless($post->team_id === $team->id && $post->status->value === 'published' && $post->published_at?->isPast(), 404);
        $post->load(['team', 'media'])->loadCount(['likes', 'comments']);

        return view('posts.show', compact('post'));
    }
}
