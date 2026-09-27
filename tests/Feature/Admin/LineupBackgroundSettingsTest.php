<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LineupBackgroundSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_admin_can_preview_upload_replace_and_remove_lineup_background(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Kadro saha arka planı')
            ->assertSee('Önerilen boyut: 1080 × 1240 px.');

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'site_name' => 'Tribünasyon',
            'match_center_lineup_background' => UploadedFile::fake()->image('pitch.jpg', 1080, 1240),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $settings = SiteSetting::current();
        $oldPath = $settings->match_center_lineup_background_path;
        $this->assertStringStartsWith('branding/match-center/', $oldPath);
        Storage::disk('public')->assertExists($oldPath);
        $this->actingAs($admin)->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee($settings->mediaUrl('match_center_lineup_background_path'), false);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'site_name' => 'Tribünasyon',
            'match_center_lineup_background' => UploadedFile::fake()->image('pitch.png', 1080, 1240),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $newPath = SiteSetting::current()->match_center_lineup_background_path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'site_name' => 'Tribünasyon',
            'remove_match_center_lineup_background' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull(SiteSetting::current()->match_center_lineup_background_path);
        Storage::disk('public')->assertMissing($newPath);
    }

    public function test_lineup_background_validation_rejects_unsafe_type_and_files_over_five_megabytes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'site_name' => 'Tribünasyon',
            'match_center_lineup_background' => UploadedFile::fake()->create('pitch.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('match_center_lineup_background');

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'site_name' => 'Tribünasyon',
            'match_center_lineup_background' => UploadedFile::fake()->image('pitch.jpg', 1080, 1240)->size(5121),
        ])->assertSessionHasErrors('match_center_lineup_background');

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_non_admin_cannot_change_lineup_background(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))
            ->put(route('admin.settings.update'), [
                'site_name' => 'Tribünasyon',
                'match_center_lineup_background' => UploadedFile::fake()->image('pitch.png', 1080, 1240),
            ])->assertForbidden();

        $this->assertDatabaseCount('site_settings', 0);
    }
}
