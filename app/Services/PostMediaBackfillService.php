<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PostMediaBackfillService
{
    public function run(): void
    {
        DB::table('posts')
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($posts): void {
                $now = now();
                $rows = $posts->map(fn ($post): array => [
                    'post_id' => $post->id,
                    'type' => 'image',
                    'path' => $post->image_path,
                    'sort_order' => 1,
                    'created_at' => $post->created_at ?? $now,
                    'updated_at' => $post->updated_at ?? $now,
                ])->all();

                DB::table('post_media')->insertOrIgnore($rows);
            });
    }
}
