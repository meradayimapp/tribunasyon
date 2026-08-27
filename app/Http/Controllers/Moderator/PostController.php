<?php

namespace App\Http\Controllers\Moderator;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Team;
use App\Services\PostMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
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

    public function store(Request $request, PostMediaService $media): RedirectResponse
    {
        $team = Team::findOrFail($request->integer('team_id'));
        $this->authorize('create', [Post::class, $team]);
        [$data, $uploads, $order] = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $media->save(new Post, $data, $uploads, $order);

        return redirect()->route('moderator.posts.index')->with('success', 'Gönderi kaydedildi.');
    }

    public function edit(Request $request, Post $post): View
    {
        $this->authorize('update', $post);

        return view('moderator.posts.form', ['post' => $post->load('media'), 'teams' => $request->user()->moderatedTeams()->orderBy('name')->get()]);
    }

    public function update(Request $request, Post $post, PostMediaService $media): RedirectResponse
    {
        $this->authorize('update', $post);
        $team = Team::findOrFail($request->integer('team_id'));
        $this->authorize('create', [Post::class, $team]);
        [$data, $uploads, $order] = $this->validated($request, $post);
        $media->save($post, $data, $uploads, $order);

        return redirect()->route('moderator.posts.index')->with('success', 'Gönderi güncellendi.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);
        $post->delete();

        return back()->with('success', 'Gönderi silindi.');
    }

    private function validated(Request $request, ?Post $post = null): array
    {
        $validator = validator($request->all(), [
            'team_id' => ['required', 'exists:teams,id'],
            'body' => ['required', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'media_order' => ['nullable', 'array', 'max:10'],
            'media_order.*' => ['required', 'string', 'distinct', 'regex:/^(existing|new):[0-9]+$/'],
            'media_editor_present' => ['nullable', 'boolean'],
        ]);
        $validator->after(function (Validator $validator) use ($request): void {
            $imageFiles = $request->file('images', []);
            $multiple = is_array($imageFiles) ? count($imageFiles) : ($imageFiles ? 1 : 0);
            $legacy = $request->hasFile('image') ? 1 : 0;

            if ($multiple + $legacy > PostMediaService::MAX_MEDIA) {
                $validator->errors()->add('images', 'Bir gönderide en fazla 10 görsel olabilir.');
            }
        });
        $data = $validator->validate();
        $uploads = array_values($request->file('images', []));

        if ($request->hasFile('image')) {
            $uploads[] = $request->file('image');
        }

        $data['published_at'] = $data['status'] === PostStatus::Published->value ? ($post?->published_at ?? now()) : null;
        $order = $request->boolean('media_editor_present') ? array_values($data['media_order'] ?? []) : null;
        unset($data['image'], $data['images'], $data['media_order'], $data['media_editor_present']);

        return [$data, $uploads, $order];
    }
}
