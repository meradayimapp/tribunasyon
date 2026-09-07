<?php

namespace Tests\Feature\Moderator;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\TeamStatus;
use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ModeratorAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderator_can_create_for_assigned_team_but_not_another_team(): void
    {
        $own = $this->team('Kendi Takımı', 'kendi');
        $other = $this->team('Başka Takım', 'baska');
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $moderator->moderatedTeams()->attach($own);

        $this->actingAs($moderator)->post(route('moderator.posts.store'), ['team_id' => $own->id, 'body' => 'Yetkili paylaşım', 'status' => 'published'])->assertRedirect(route('moderator.posts.index'));
        $this->assertDatabaseHas('posts', ['team_id' => $own->id, 'created_by' => $moderator->id]);
        $this->actingAs($moderator)->post(route('moderator.posts.store'), ['team_id' => $other->id, 'body' => 'Yetkisiz paylaşım', 'status' => 'published'])->assertForbidden();
    }

    public function test_member_cannot_open_moderator_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/moderator')->assertForbidden();
    }

    public function test_post_upload_rejects_svg(): void
    {
        $team = $this->team('Takım', 'takim');
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $moderator->moderatedTeams()->attach($team);
        $upload = UploadedFile::fake()->create('danger.svg', 12, 'image/svg+xml');

        $this->actingAs($moderator)->post(route('moderator.posts.store'), ['team_id' => $team->id, 'body' => 'Görsel', 'status' => 'published', 'image' => $upload])->assertSessionHasErrors('image');
    }

    public function test_moderator_cannot_edit_post_from_other_team(): void
    {
        $own = $this->team('Kendi', 'kendi');
        $other = $this->team('Başka', 'baska');
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $moderator->moderatedTeams()->attach($own);
        $post = Post::create(['team_id' => $other->id, 'created_by' => $moderator->id, 'type' => PostType::Text, 'body' => 'Başka takım gönderisi', 'status' => PostStatus::Published, 'published_at' => now()]);

        $this->actingAs($moderator)->get(route('moderator.posts.edit', $post))->assertForbidden();
    }

    public function test_moderator_can_delete_member_comment_but_cannot_delete_admin_comment(): void
    {
        $team = $this->team('Takım', 'takim');
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $member = User::factory()->create();
        $moderator->moderatedTeams()->attach($team);
        $post = Post::create(['team_id' => $team->id, 'created_by' => $moderator->id, 'type' => PostType::Text, 'body' => 'Gönderi', 'status' => PostStatus::Published, 'published_at' => now()]);
        $memberComment = Comment::create(['post_id' => $post->id, 'user_id' => $member->id, 'body' => 'Üye yorumu']);
        $adminComment = Comment::create(['post_id' => $post->id, 'user_id' => $admin->id, 'body' => 'Admin yorumu']);

        $this->actingAs($moderator)->delete(route('moderator.comments.destroy', $memberComment))->assertRedirect();
        $this->assertSoftDeleted($memberComment);

        $this->actingAs($moderator)->delete(route('moderator.comments.destroy', $adminComment))->assertForbidden();
        $this->assertNotSoftDeleted($adminComment);

        $this->actingAs($admin)->delete(route('admin.comments.destroy', $adminComment))->assertRedirect();
        $this->assertSoftDeleted($adminComment);
    }

    private function team(string $name, string $slug): Team
    {
        return Team::create(['name' => $name, 'slug' => $slug, 'short_name' => strtoupper(substr($slug, 0, 3)), 'primary_color' => '#111111', 'secondary_color' => '#ffffff', 'status' => TeamStatus::Active]);
    }
}
