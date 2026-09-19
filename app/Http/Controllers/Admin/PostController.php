<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Team;
use App\Services\PostEditorInput;
use App\Services\PostMediaService;
use App\Services\PostSourceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $posts = Post::withTrashed()->with(['team' => fn ($query) => $query->withTrashed(), 'creator'])->withCount(['likes', 'comments'])
            ->when($request->filled('team'), fn ($query) => $query->where('team_id', $request->integer('team')))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.posts.index', compact('posts'));
    }

    public function create(): View
    {
        return view('moderator.posts.form', [
            'post' => new Post,
            'teams' => Team::active()->ordered()->get(),
            'scope' => 'admin',
        ]);
    }

    public function store(Request $request, PostMediaService $media, PostEditorInput $input): RedirectResponse
    {
        $team = Team::active()->findOrFail($request->integer('team_id'));
        $this->authorize('create', [Post::class, $team]);
        [$data, $uploads, $order, $sourceRows] = $input->validated($request);
        $data['created_by'] = $request->user()->id;
        $media->save(new Post, $data, $uploads, $order, $sourceRows);

        return redirect()->route('admin.posts.index')->with('success', 'Gönderi kaydedildi.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return back()->with('success', 'Gönderi silindi.');
    }

    public function edit(Post $post): View
    {
        return view('admin.posts.edit', [
            'post' => $post->load(['team' => fn ($query) => $query->withTrashed(), 'sources']),
        ]);
    }

    public function update(Request $request, Post $post, PostSourceService $sources): RedirectResponse
    {
        $data = $request->validate([
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            ...$sources->validationRules(),
        ]);
        $sourceRows = $request->boolean('sources_editor_present') || $request->exists('sources')
            ? $sources->normalize($data['sources'] ?? [])
            : null;

        DB::transaction(function () use ($post, $data, $sources, $sourceRows): void {
            $post->update([
                'seo_title' => filled($data['seo_title'] ?? null) ? trim($data['seo_title']) : null,
                'seo_description' => filled($data['seo_description'] ?? null) ? trim($data['seo_description']) : null,
            ]);
            if ($sourceRows !== null) {
                $sources->sync($post, $sourceRows);
            }
        });

        return redirect()->route('admin.posts.index')->with('success', 'Gönderi alanları güncellendi.');
    }

    public function restore(int $post): RedirectResponse
    {
        Post::withTrashed()->findOrFail($post)->restore();

        return back()->with('success', 'Gönderi geri alındı.');
    }
}
