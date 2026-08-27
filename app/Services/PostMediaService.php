<?php

namespace App\Services;

use App\Enums\PostType;
use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PostMediaService
{
    public const MAX_MEDIA = 10;

    public function __construct(private readonly MediaStorageService $storage) {}

    public function save(Post $post, array $attributes, array $uploads, ?array $requestedOrder): Post
    {
        $existing = $post->exists ? $post->media()->get()->keyBy('id') : collect();
        $order = $this->normalizeOrder($existing, $uploads, $requestedOrder);
        $storedPaths = [];

        try {
            foreach ($uploads as $upload) {
                $storedPaths[] = $this->storage->store($upload, 'posts');
            }

            $removedPaths = DB::transaction(function () use ($post, $attributes, $existing, $order, $storedPaths): array {
                $attributes['type'] = count($order) > 0 ? PostType::Image : PostType::Text;
                $attributes['image_path'] = null;
                $post->fill($attributes)->save();

                if ($existing->isNotEmpty()) {
                    $post->media()->update(['sort_order' => DB::raw('sort_order + 100')]);
                }

                $keptIds = collect($order)
                    ->filter(fn (string $token): bool => str_starts_with($token, 'existing:'))
                    ->map(fn (string $token): int => (int) str($token)->after('existing:')->toString());
                $removed = $existing->reject(fn (PostMedia $media): bool => $keptIds->containsStrict($media->id));

                if ($removed->isNotEmpty()) {
                    PostMedia::whereKey($removed->pluck('id'))->delete();
                }

                foreach ($order as $position => $token) {
                    $sortOrder = $position + 1;

                    if (str_starts_with($token, 'existing:')) {
                        $existingId = (int) str($token)->after('existing:')->toString();
                        PostMedia::whereKey($existingId)->where('post_id', $post->id)->update(['sort_order' => $sortOrder]);

                        continue;
                    }

                    $newIndex = (int) str($token)->after('new:')->toString();
                    $post->media()->create(['type' => 'image', 'path' => $storedPaths[$newIndex], 'sort_order' => $sortOrder]);
                }

                return $removed->pluck('path')->all();
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }

        foreach ($removedPaths as $path) {
            $this->deleteIfUnused($path);
        }

        return $post->refresh()->load('media');
    }

    private function normalizeOrder(Collection $existing, array $uploads, ?array $requestedOrder): array
    {
        $order = $requestedOrder ?? [
            ...$existing->keys()->map(fn ($id): string => "existing:{$id}")->all(),
            ...array_map(fn (int $index): string => "new:{$index}", array_keys($uploads)),
        ];

        if (count($order) > self::MAX_MEDIA) {
            throw ValidationException::withMessages(['images' => 'Bir gönderide en fazla 10 görsel olabilir.']);
        }

        $allowedExistingIds = $existing->keys()->map(fn ($id): int => (int) $id)->all();
        $newIndexes = [];

        foreach ($order as $token) {
            if (str_starts_with($token, 'existing:')) {
                $id = (int) str($token)->after('existing:')->toString();

                if (! in_array($id, $allowedExistingIds, true)) {
                    throw ValidationException::withMessages(['media_order' => 'Seçilen medya bu gönderiye ait değil.']);
                }

                continue;
            }

            if (! str_starts_with($token, 'new:')) {
                throw ValidationException::withMessages(['media_order' => 'Medya sıralaması geçersiz.']);
            }

            $newIndexes[] = (int) str($token)->after('new:')->toString();
        }

        sort($newIndexes);
        $expectedIndexes = count($uploads) > 0 ? range(0, count($uploads) - 1) : [];

        if ($newIndexes !== $expectedIndexes) {
            throw ValidationException::withMessages(['media_order' => 'Yeni görsellerin sıralaması geçersiz.']);
        }

        return array_values($order);
    }

    private function deleteIfUnused(string $path): void
    {
        if (! PostMedia::where('path', $path)->exists()) {
            Storage::disk('public')->delete($path);
        }
    }
}
