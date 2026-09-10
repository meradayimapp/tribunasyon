<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return back()->with('success', 'Gönderi silindi.');
    }

    public function edit(Post $post): View
    {
        return view('admin.posts.edit', [
            'post' => $post->load(['team' => fn ($query) => $query->withTrashed()]),
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate([
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
        ]);

        $post->update([
            'seo_title' => filled($data['seo_title'] ?? null) ? trim($data['seo_title']) : null,
            'seo_description' => filled($data['seo_description'] ?? null) ? trim($data['seo_description']) : null,
        ]);

        return redirect()->route('admin.posts.index')->with('success', 'SEO alanları güncellendi.');
    }

    public function restore(int $post): RedirectResponse
    {
        Post::withTrashed()->findOrFail($post)->restore();

        return back()->with('success', 'Gönderi geri alındı.');
    }
}
