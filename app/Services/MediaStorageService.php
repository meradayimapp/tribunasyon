<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaStorageService
{
    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    public function replace(?string $oldPath, UploadedFile $file, string $directory): string
    {
        $path = $this->store($file, $directory);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $path;
    }
}
