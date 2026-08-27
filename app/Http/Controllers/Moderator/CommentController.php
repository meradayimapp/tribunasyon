<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommentController extends Controller
{
    public function index(Request $request): View
    {
        $teamIds = $request->user()->moderatedTeams()->pluck('teams.id');
        $comments = Comment::with(['user', 'post.team'])->whereHas('post', fn ($query) => $query->whereIn('team_id', $teamIds))->latest()->paginate(25);

        return view('moderator.comments.index', compact('comments'));
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);
        $comment->delete();

        return back()->with('success', 'Yorum kaldırıldı.');
    }
}
