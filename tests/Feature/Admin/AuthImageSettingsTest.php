<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthImageSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_admin_can_upload_auth_images_and_each_page_uses_its_own_image(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'site_name' => 'Tribünasyon',
            'login_image' => UploadedFile::fake()->image('login.jpg', 1200, 1600),
            'register_image' => UploadedFile::fake()->image('register.webp', 1200, 1600),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $settings = SiteSetting::current();
        $this->assertStringStartsWith('branding/auth/', $settings->login_image_path);
        $this->assertStringStartsWith('branding/auth/', $settings->register_image_path);
        Storage::disk('public')->assertExists($settings->login_image_path);
        Storage::disk('public')->assertExists($settings->register_image_path);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->get(route('login'))->assertOk()->assertSee($settings->mediaUrl('login_image_path'), false);
        $this->get(route('register'))->assertOk()->assertSee($settings->mediaUrl('register_image_path'), false);
    }

    public function test_replacing_auth_image_uses_a_unique_path_and_removes_old_file(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $upload = fn () => $this->actingAs($admin)->put(route('admin.settings.update'), [
            'site_name' => 'Tribünasyon',
            'login_image' => UploadedFile::fake()->image('same-name.png', 900, 1200),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $upload();
        $oldPath = SiteSetting::current()->login_image_path;
        $upload();
        $newPath = SiteSetting::current()->login_image_path;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_non_admin_cannot_upload_auth_images(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))
            ->put(route('admin.settings.update'), [
                'site_name' => 'Tribünasyon',
                'login_image' => UploadedFile::fake()->image('forbidden.png'),
            ])->assertForbidden();

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_admin_can_remove_auth_images_and_auth_pages_use_css_fallback(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'site_name' => 'Tribünasyon',
            'login_image' => UploadedFile::fake()->image('login.png'),
            'register_image' => UploadedFile::fake()->image('register.png'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $oldPaths = [SiteSetting::current()->login_image_path, SiteSetting::current()->register_image_path];

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'site_name' => 'Tribünasyon',
            'remove_login_image' => '1',
            'remove_register_image' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $settings = SiteSetting::current();
        $this->assertNull($settings->login_image_path);
        $this->assertNull($settings->register_image_path);
        Storage::disk('public')->assertMissing($oldPaths);

        $this->post(route('logout'));
        $this->get(route('login'))->assertOk()->assertSee('auth-visual-fallback', false);
        $this->get(route('register'))->assertOk()->assertSee('auth-visual-fallback', false);
    }
}
