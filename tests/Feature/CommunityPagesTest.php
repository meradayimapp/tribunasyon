<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\TeamStatus;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_team_and_post_without_moderator_identity(): void
    {
        [$team, $post, $moderator] = $this->content();
        $this->get(route('teams.show', $team))->assertOk()->assertSee($post->body)->assertDontSee($moderator->username);
        $this->get(route('posts.show', [$team, $post]))->assertOk()->assertSee($post->body)->assertDontSee($moderator->username);
        $this->assertArrayNotHasKey('created_by', $post->toArray());
    }

    public function test_matches_page_uses_working_mock_data(): void
    {
        $this->get('/maclar')->assertOk()->assertSee('Bugünün maçları')->assertSee('Fenerbahçe')->assertSee('CANLI');
    }

    public function test_followed_team_feed_excludes_other_teams(): void
    {
        [$team, $post] = $this->content();
        $other = Team::create(['name' => 'Diğer', 'slug' => 'diger', 'short_name' => 'DGR', 'primary_color' => '#222222', 'secondary_color' => '#eeeeee', 'status' => TeamStatus::Active]);
        $otherPost = Post::create(['team_id' => $other->id, 'created_by' => $post->created_by, 'type' => PostType::Text, 'body' => 'Görünmemesi gereken gönderi', 'status' => PostStatus::Published, 'published_at' => now()]);
        $member = User::factory()->create();
        $member->followedTeams()->attach($team);

        $this->actingAs($member)->get('/')->assertOk()->assertSee($post->body)->assertDontSee($otherPost->body);
    }

    private function content(): array
    {
        $team = Team::create(['name' => 'Takım', 'slug' => 'takim', 'short_name' => 'TKM', 'primary_color' => '#111111', 'secondary_color' => '#ffffff', 'status' => TeamStatus::Active]);
        $moderator = User::factory()->create(['name' => 'Gizli Moderatör', 'username' => 'gizli.mod']);
        $post = Post::create(['team_id' => $team->id, 'created_by' => $moderator->id, 'type' => PostType::Text, 'body' => 'Topluluk gönderisi', 'status' => PostStatus::Published, 'published_at' => now()]);

        return [$team, $post, $moderator];
    }
}
