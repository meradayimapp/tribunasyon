<?php

namespace Tests\Feature\Livewire;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\TeamStatus;
use App\Livewire\Comments;
use App\Livewire\FollowTeam;
use App\Livewire\PostActions;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SocialInteractionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_toggle_post_like_without_duplicates(): void
    {
        [$team, $post, $user] = $this->records();
        Livewire::actingAs($user)->test(PostActions::class, ['post' => $post])->call('toggleLike');
        $this->assertDatabaseCount('likes', 1);
        Livewire::actingAs($user)->test(PostActions::class, ['post' => $post])->call('toggleLike');
        $this->assertDatabaseCount('likes', 0);
    }

    public function test_member_can_follow_and_unfollow_team(): void
    {
        [$team, , $user] = $this->records();
        Livewire::actingAs($user)->test(FollowTeam::class, ['team' => $team])->call('toggle');
        $this->assertDatabaseHas('team_follows', ['user_id' => $user->id, 'team_id' => $team->id]);
        Livewire::actingAs($user)->test(FollowTeam::class, ['team' => $team])->call('toggle');
        $this->assertDatabaseMissing('team_follows', ['user_id' => $user->id, 'team_id' => $team->id]);
    }

    public function test_comments_allow_only_one_reply_level(): void
    {
        [, $post, $user] = $this->records();
        Livewire::actingAs($user)->test(Comments::class, ['post' => $post])->set('body', 'Ana yorum')->call('submit')->assertHasNoErrors();
        $root = Comment::firstOrFail();
        Livewire::actingAs($user)->test(Comments::class, ['post' => $post])->call('replyTo', $root->id)->set('body', 'Yanıt')->call('submit')->assertHasNoErrors();
        $reply = Comment::whereNotNull('parent_id')->firstOrFail();
        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($user)->test(Comments::class, ['post' => $post])->call('replyTo', $reply->id);
    }

    private function records(): array
    {
        $team = Team::create(['name' => 'Takım', 'slug' => 'takim', 'short_name' => 'TKM', 'primary_color' => '#111111', 'secondary_color' => '#ffffff', 'status' => TeamStatus::Active]);
        $user = User::factory()->create();
        $post = Post::create(['team_id' => $team->id, 'created_by' => $user->id, 'type' => PostType::Text, 'body' => 'Gönderi', 'status' => PostStatus::Published, 'published_at' => now()]);

        return [$team, $post, $user];
    }
}
