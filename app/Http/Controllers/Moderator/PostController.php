<?php

namespace App\Http\Controllers\Moderator;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Team;
use App\Services\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        return view('moderator.posts.form', ['post' => new Post, 'teams' => $request->user()->moderatedTeams()->orderBy('name')->get()]);
    }

    public function store(Request $request, MediaStorageService $media): RedirectResponse
    {
        $team = Team::findOrFail($request->integer('team_id'));
        $this->authorize('create', [Post::class, $team]);
        $data = $this->validated($request, $media);
        $data['created_by'] = $request->user()->id;
        Post::create($data);

        return redirect()->route('moderator.posts.index')->with('success', 'Gönderi kaydedildi.');
    }

    public function edit(Request $request, Post $post): View
    {
        $this->authorize('update', $post);

        return view('moderator.posts.form', ['post' => $post, 'teams' => $request->user()->moderatedTeams()->orderBy('name')->get()]);
    }

    public function update(Request $request, Post $post, MediaStorageService $media): RedirectResponse
    {
        $this->authorize('update', $post);
        $post->update($this->validated($request, $media, $post));

        return redirect()->route('moderator.posts.index')->with('success', 'Gönderi güncellendi.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);
        $post->delete();

        return back()->with('success', 'Gönderi silindi.');
    }

    private function validated(Request $request, MediaStorageService $media, ?Post $post = null): array
    {
        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'body' => ['required', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
        ]);
        if ($request->hasFile('image')) {
            $data['image_path'] = $media->replace($post?->image_path, $request->file('image'), 'posts');
        }
        $data['type'] = isset($data['image_path']) || $post?->image_path ? PostType::Image : PostType::Text;
        $data['published_at'] = $data['status'] === PostStatus::Published->value ? ($post?->published_at ?? now()) : null;
        unset($data['image']);

        return $data;
    }
}
