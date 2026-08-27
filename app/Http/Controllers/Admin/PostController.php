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

    public function restore(int $post): RedirectResponse
    {
        Post::withTrashed()->findOrFail($post)->restore();

        return back()->with('success', 'Gönderi geri alındı.');
    }
}
