<?php

namespace Tests\Feature;

use App\Services\MediaStorageService;
use App\Services\MediaUrlResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageRoutingTest extends TestCase
{
    public function test_r2_mode_routes_new_and_legacy_keys_to_the_correct_disks(): void
    {
        Storage::fake('public');
        Storage::fake('r2');
        config()->set('media.disk', 'r2');
        config()->set('media.legacy_disk', 'public');
        config()->set('media.r2_prefix', 'r2/v1');

        $resolver = app(MediaUrlResolver::class);

        $this->assertSame('r2', $resolver->diskNameForPath('r2/v1/posts/new.jpg'));
        $this->assertSame('public', $resolver->diskNameForPath('posts/legacy.jpg'));
        $this->assertSame('r2/v1/posts', $resolver->writeDirectory('/posts/'));
        $this->assertTrue($resolver->isManagedPath('posts/legacy.jpg', 'posts'));
        $this->assertTrue($resolver->isManagedPath('r2/v1/posts/new.jpg', 'posts'));
        $this->assertFalse($resolver->isManagedPath('r2/v1/avatars/new.jpg', 'posts'));
    }

    public function test_r2_uploads_are_prefixed_and_legacy_deletes_stay_on_public_disk(): void
    {
        Storage::fake('public');
        Storage::fake('r2');
        config()->set('media.disk', 'r2');
        config()->set('media.legacy_disk', 'public');
        config()->set('media.r2_prefix', 'r2/v1');

        Storage::disk('public')->put('avatars/legacy.jpg', 'legacy');

        $storage = app(MediaStorageService::class);
        $path = $storage->store(UploadedFile::fake()->image('avatar.jpg'), 'avatars');

        $this->assertStringStartsWith('r2/v1/avatars/', $path);
        Storage::disk('r2')->assertExists($path);

        $storage->delete('avatars/legacy.jpg');

        Storage::disk('public')->assertMissing('avatars/legacy.jpg');
    }
}
