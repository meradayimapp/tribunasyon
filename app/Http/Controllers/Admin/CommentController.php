<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CommentController extends Controller
{
    public function index(): View
    {
        return view('admin.comments.index', ['comments' => Comment::withTrashed()->with(['user', 'post.team' => fn ($query) => $query->withTrashed()])->latest()->paginate(25)]);
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $comment->delete();

        return back()->with('success', 'Yorum silindi.');
    }

    public function restore(int $comment): RedirectResponse
    {
        Comment::withTrashed()->findOrFail($comment)->restore();

        return back()->with('success', 'Yorum geri alındı.');
    }
}
