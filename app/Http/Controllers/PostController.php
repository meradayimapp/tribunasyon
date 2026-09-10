<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Team;
use App\Services\SeoService;
use Illuminate\View\View;

class PostController extends Controller
{
    public function show(Team $team, Post $post, SeoService $seoService): View
    {
        abort_unless($post->team_id === $team->id && $post->status->value === 'published' && $post->published_at?->isPast(), 404);
        $post->load(['team.organization', 'media'])->loadCount(['likes', 'comments']);

        return view('posts.show', ['post' => $post, 'seo' => $seoService->post($post)]);
    }
}
