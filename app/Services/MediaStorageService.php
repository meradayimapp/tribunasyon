<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class MediaStorageService
{
    public function __construct(private readonly MediaUrlResolver $resolver) {}

    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($this->resolver->writeDirectory($directory), [
            'disk' => $this->resolver->diskName(),
            'CacheControl' => config('media.cache_control'),
            'ContentType' => $file->getMimeType(),
        ]);
    }

    public function replace(?string $oldPath, UploadedFile $file, string $directory): string
    {
        $path = $this->store($file, $directory);

        if ($oldPath) {
            $this->resolver->disk($oldPath)->delete($oldPath);
        }

        return $path;
    }

    public function delete(string|array|null $paths): void
    {
        foreach ((array) $paths as $path) {
            if (filled($path)) {
                $this->resolver->disk($path)->delete($path);
            }
        }
    }

    public function isManagedPath(?string $path, string $directory): bool
    {
        return $this->resolver->isManagedPath($path, $directory);
    }
}
