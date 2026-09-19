<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Team;
use App\Services\PostEditorInput;
use App\Services\PostMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $teamIds = $request->user()->moderatedTeams()->pluck('teams.id');

        return view('moderator.posts.index', ['posts' => Post::whereIn('team_id', $teamIds)->with('team')->withCount(['likes', 'comments'])->latest()->paginate(20)]);
    }

    public function create(Request $request): View
    {
        return view('moderator.posts.form', ['post' => new Post, 'teams' => $request->user()->moderatedTeams()->ordered()->get()]);
    }

    public function store(Request $request, PostMediaService $media, PostEditorInput $input): RedirectResponse
    {
        $team = Team::findOrFail($request->integer('team_id'));
        $this->authorize('create', [Post::class, $team]);
        [$data, $uploads, $order, $sourceRows] = $input->validated($request);
        $data['created_by'] = $request->user()->id;
        $media->save(new Post, $data, $uploads, $order, $sourceRows);

        return redirect()->route('moderator.posts.index')->with('success', 'Gönderi kaydedildi.');
    }

    public function edit(Request $request, Post $post): View
    {
        $this->authorize('update', $post);

        return view('moderator.posts.form', ['post' => $post->load(['media', 'sources']), 'teams' => $request->user()->moderatedTeams()->ordered()->get()]);
    }

    public function update(Request $request, Post $post, PostMediaService $media, PostEditorInput $input): RedirectResponse
    {
        $this->authorize('update', $post);
        $team = Team::findOrFail($request->integer('team_id'));
        $this->authorize('create', [Post::class, $team]);
        [$data, $uploads, $order, $sourceRows] = $input->validated($request, $post);
        $media->save($post, $data, $uploads, $order, $sourceRows);

        return redirect()->route('moderator.posts.index')->with('success', 'Gönderi güncellendi.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);
        $post->delete();

        return back()->with('success', 'Gönderi silindi.');
    }
}
