<?php

namespace App\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrganizationBadgeCatalog
{
    private const DIRECTORY = 'organizations-images';

    private const ALLOWED_EXTENSIONS = ['png', 'svg', 'webp'];

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
                    'path' => $path,
                    'label' => $this->label($filename),
                    'url' => asset('storage/'.$path),
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function paths(): array
    {
        return $this->all()->pluck('path')->all();
    }

    public function find(?string $path): ?array
    {
        if ($path === null) {
            return null;
        }

        return $this->all()->firstWhere('path', $path);
    }

    private function label(string $filename): string
    {
        $label = Str::of(pathinfo($filename, PATHINFO_FILENAME))
            ->replace(['-', '_'], ' ')
            ->squish()
            ->title()
            ->toString();

        return str_replace(['Uefa', 'Sampiyonlar'], ['UEFA', 'Şampiyonlar'], $label);
    }
}
