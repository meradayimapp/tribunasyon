<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUrlResolver
{
    public function diskName(): string
    {
        return (string) config('media.disk', 'public');
    }

    public function disk(?string $path = null): Filesystem
    {
        return Storage::disk($path === null ? $this->diskName() : $this->diskNameForPath($path));
    }

    public function diskNameForPath(string $path): string
    {
        if ($this->diskName() !== 'r2') {
            return $this->diskName();
        }

        return Str::startsWith(ltrim($path, '/'), $this->r2Prefix().'/')
            ? 'r2'
            : (string) config('media.legacy_disk', 'public');
    }

    public function writeDirectory(string $directory): string
    {
        $directory = trim($directory, '/');

        if ($this->diskName() !== 'r2') {
            return $directory;
        }

        return $this->r2Prefix().'/'.$directory;
    }

    public function isManagedPath(?string $path, string $directory): bool
    {
        if (! filled($path)) {
            return false;
        }

        $path = ltrim((string) $path, '/');
        $prefix = $this->r2Prefix().'/';

        if (Str::startsWith($path, $prefix)) {
            $path = Str::after($path, $prefix);
        }

        return Str::startsWith($path, trim($directory, '/').'/');
    }

    public function url(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $path = ltrim($path, '/');

        return $this->disk($path)->url($path);
    }

    public function exists(?string $path): bool
    {
        if (! filled($path)) {
            return false;
        }

        $path = ltrim($path, '/');

        return $this->disk($path)->exists($path);
    }

    private function r2Prefix(): string
    {
        return trim((string) config('media.r2_prefix', 'r2/v1'), '/');
    }
}
