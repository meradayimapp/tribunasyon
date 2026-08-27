<?php

namespace Tests\Feature\Admin;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\TeamStatus;
use App\Enums\UserRole;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->get('/admin')->assertOk();
    }

    public function test_admin_can_see_post_creator_and_delete_restore_post(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $moderator = User::factory()->create(['name' => 'İçerik Moderatörü', 'username' => 'icerik.mod', 'role' => UserRole::Moderator]);
        $team = $this->team();
        $post = Post::create(['team_id' => $team->id, 'created_by' => $moderator->id, 'type' => PostType::Text, 'body' => 'Yönetilecek gönderi', 'status' => PostStatus::Published, 'published_at' => now()]);

        $this->actingAs($admin)->get(route('admin.posts.index'))->assertOk()->assertSee('icerik.mod');
        $this->actingAs($admin)->delete(route('admin.posts.destroy', $post))->assertRedirect();
        $this->assertSoftDeleted($post);
        $this->actingAs($admin)->post(route('admin.posts.restore', $post->id))->assertRedirect();
        $this->assertNotSoftDeleted($post->fresh());
    }

    public function test_admin_can_suspend_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $member = User::factory()->create();
        $this->actingAs($admin)->put(route('admin.users.update', $member), ['role' => 'member', 'status' => 'suspended', 'favorite_team_id' => null])->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $member->id, 'status' => 'suspended']);
    }

    private function team(): Team
    {
        return Team::create(['name' => 'Takım', 'slug' => 'takim', 'short_name' => 'TKM', 'primary_color' => '#111111', 'secondary_color' => '#ffffff', 'status' => TeamStatus::Active]);
    }
}
