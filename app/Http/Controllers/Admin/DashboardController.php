<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', ['stats' => [
            'teams' => Team::count(),
            'members' => User::where('role', UserRole::Member)->count(),
            'moderators' => User::where('role', UserRole::Moderator)->count(),
            'posts' => Post::where('status', PostStatus::Published)->count(),
            'comments' => Comment::count(),
        ]]);
    }
}
