<?php

namespace App\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class TeamLogoCatalog
{
    private const DIRECTORY = 'teams/logos';

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(private readonly Filesystem $files) {}

    public function all(): Collection
    {
        $directory = realpath(public_path('storage/'.self::DIRECTORY));

        if ($directory === false || ! is_dir($directory)) {
            return collect();
        }

        return collect($this->files->files($directory))
            ->filter(function ($file) use ($directory): bool {
                $realPath = $file->getRealPath();
                $extension = strtolower($file->getExtension());

                return $realPath !== false
                    && str_starts_with($realPath, $directory.DIRECTORY_SEPARATOR)
                    && in_array($extension, self::ALLOWED_EXTENSIONS, true);
            })
            ->map(function ($file): array {
                $filename = $file->getFilename();
                $path = self::DIRECTORY.'/'.$filename;

                return [
                    'filename' => $filename,
                    'path' => $path,
                    'url' => asset('storage/'.$path),
                ];
            })
            ->sortBy('filename', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function paths(): array
    {
        return $this->all()->pluck('path')->all();
    }

    public function selectionFor(?string $currentPath): ?string
    {
        if ($currentPath === null) {
            return null;
        }

        $logos = $this->all();
        $exactMatch = $logos->firstWhere('path', $currentPath);

        if ($exactMatch !== null) {
            return $exactMatch['path'];
        }

        return $logos->first(
            fn (array $logo): bool => basename($logo['path']) === basename($currentPath)
        )['path'] ?? null;
    }
}
