<?php

namespace App\Observers;

use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PostObserver
{
    public function deleting(Post $post): void
    {
        if ($post->isForceDeleting()) {
            $post->setRelation('mediaPendingPurge', $post->media()->get(['id', 'post_id', 'path']));
        }
    }

    public function forceDeleted(Post $post): void
    {
        $paths = $post->getRelation('mediaPendingPurge')->pluck('path')->unique()->values()->all();

        DB::afterCommit(function () use ($paths): void {
            foreach ($paths as $path) {
                if (! PostMedia::where('path', $path)->exists()) {
                    Storage::disk('public')->delete($path);
                }
            }
        });
    }
}
