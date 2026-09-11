<?php

namespace Tests\Feature\Admin;

use App\Enums\PlayerStatus;
use App\Enums\UserRole;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlayerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_manage_player_master_data(): void
    {
        $member = User::factory()->create();
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        $this->actingAs($member)->get(route('admin.players.create'))->assertForbidden();
        $this->actingAs($moderator)->post(route('admin.players.store'), $this->payload())->assertForbidden();
    }

    public function test_admin_can_create_update_delete_and_restore_player(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.players.store'), $this->payload())
            ->assertRedirect(route('admin.players.index'));
        $player = Player::firstOrFail();
        $this->assertSame('€75M', $player->formatted_market_value);
        $this->assertSame('Türkiye', $player->nationality);

        $this->actingAs($admin)->put(route('admin.players.update', $player), [
            ...$this->payload(), 'name' => 'Güncel Oyuncu', 'status' => 'inactive',
        ])->assertRedirect(route('admin.players.index'));
        $this->assertSame('Güncel Oyuncu', $player->fresh()->name);
        $this->assertSame(PlayerStatus::Inactive, $player->fresh()->status);

        $this->actingAs($admin)->delete(route('admin.players.destroy', $player))->assertRedirect();
        $this->assertSoftDeleted($player);
        $this->actingAs($admin)->post(route('admin.players.restore', $player->id))->assertRedirect();
        $this->assertNotSoftDeleted($player->fresh());
    }

    public function test_slug_must_be_unique(): void
    {
        Player::factory()->create(['slug' => 'benzersiz']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.players.store'), [
            ...$this->payload(), 'slug' => 'benzersiz',
        ])->assertSessionHasErrors('slug');
    }

    public function test_player_media_uses_public_storage_and_can_be_replaced_and_removed(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.players.store'), [
            ...$this->payload(),
            'photo_file' => UploadedFile::fake()->image('photo.jpg', 400, 600),
            'cover_file' => UploadedFile::fake()->image('cover.webp', 1200, 500),
        ])->assertSessionHasNoErrors();
        $player = Player::firstOrFail();
        $oldPhoto = $player->photo_path;
        Storage::disk('public')->assertExists($oldPhoto);
        Storage::disk('public')->assertExists($player->cover_image_path);

        $this->actingAs($admin)->put(route('admin.players.update', $player), [
            ...$this->payload(),
            'photo_file' => UploadedFile::fake()->image('new.png', 400, 600),
            'remove_cover' => true,
        ])->assertSessionHasNoErrors();
        $player->refresh();
        Storage::disk('public')->assertMissing($oldPhoto);
        Storage::disk('public')->assertExists($player->photo_path);
        $this->assertNull($player->cover_image_path);
    }

    public function test_svg_player_media_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.players.store'), [
            ...$this->payload(),
            'photo_file' => UploadedFile::fake()->create('unsafe.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('photo_file');
    }

    private function payload(): array
    {
        return [
            'name' => 'Deneme Oyuncu',
            'slug' => 'deneme-oyuncu',
            'position' => 'Forvet',
            'shirt_number' => 9,
            'nationality' => 'Türkiye',
            'national_team_name' => 'Türkiye',
            'national_team_code' => 'tr',
            'birth_date' => '2000-01-01',
            'market_value_amount' => 75_000_000,
            'market_value_currency' => 'eur',
            'bio' => 'Güvenli biyografi.',
            'status' => 'active',
            'sort_order' => 0,
        ];
    }
}
