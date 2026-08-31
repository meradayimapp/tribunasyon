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

class ChronologicalFeedTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create();
    }

    public function test_followed_teams_published_post_is_visible(): void
    {
        $team = $this->team('Takip Edilen');
        $post = $this->makePost($team, 'Takip edilen takımın yeni gönderisi');
        $member = User::factory()->create();
        $member->followedTeams()->attach($team);

        $this->actingAs($member)->get(route('home'))->assertOk()->assertSee($post->body);
    }

    public function test_unfollowed_teams_post_is_hidden_when_user_follows_a_team(): void
    {
        $followed = $this->team('Takip Edilen');
        $unfollowed = $this->team('Takip Edilmeyen');
        $visiblePost = $this->makePost($followed, 'Görünür takip gönderisi');
        $hiddenPost = $this->makePost($unfollowed, 'Gizli takip dışı gönderi');
        $member = User::factory()->create();
        $member->followedTeams()->attach($followed);

        $this->actingAs($member)
            ->get(route('home'))
            ->assertOk()
            ->assertSee($visiblePost->body)
            ->assertDontSee($hiddenPost->body);
    }

    public function test_user_without_followed_teams_sees_posts_from_all_active_teams(): void
    {
        $firstPost = $this->makePost($this->team('Birinci Aktif'), 'Birinci aktif takım gönderisi');
        $secondPost = $this->makePost($this->team('İkinci Aktif'), 'İkinci aktif takım gönderisi');
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('home'))
            ->assertOk()
            ->assertSee($firstPost->body)
            ->assertSee($secondPost->body);
    }

    public function test_guest_sees_published_posts_from_all_active_teams(): void
    {
        $firstPost = $this->makePost($this->team('Misafir Bir'), 'Misafir için birinci gönderi');
        $secondPost = $this->makePost($this->team('Misafir İki'), 'Misafir için ikinci gönderi');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($firstPost->body)
            ->assertSee($secondPost->body);
    }

    public function test_draft_post_is_not_visible(): void
    {
        $post = $this->makePost($this->team('Taslak Takımı'), 'Akışta görünmeyen taslak', PostStatus::Draft);

        $this->get(route('home'))->assertOk()->assertDontSee($post->body);
    }

    public function test_archived_post_is_not_visible(): void
    {
        $post = $this->makePost($this->team('Arşiv Takımı'), 'Akışta görünmeyen arşiv', PostStatus::Archived);

        $this->get(route('home'))->assertOk()->assertDontSee($post->body);
    }

    public function test_soft_deleted_post_is_not_visible(): void
    {
        $post = $this->makePost($this->team('Silinen Post Takımı'), 'Akışta görünmeyen silinmiş gönderi');
        $post->delete();

        $this->get(route('home'))->assertOk()->assertDontSee($post->body);
    }

    public function test_inactive_teams_post_is_not_visible(): void
    {
        $team = $this->team('Pasif Takım', TeamStatus::Inactive);
        $post = $this->makePost($team, 'Akışta görünmeyen pasif takım gönderisi');

        $this->get(route('home'))->assertOk()->assertDontSee($post->body);
    }

    public function test_future_published_post_is_not_visible(): void
    {
        $post = $this->makePost(
            $this->team('Gelecek Takımı'),
            'Akışta görünmeyen zamanlanmış gönderi',
            PostStatus::Published,
            now()->addHour(),
        );

        $this->get(route('home'))->assertOk()->assertDontSee($post->body);
    }

    public function test_post_without_published_at_is_not_visible(): void
    {
        $post = $this->makePost(
            $this->team('Tarihsiz Takım'),
            'Akışta görünmeyen tarihsiz gönderi',
            PostStatus::Published,
            null,
        );

        $this->get(route('home'))->assertOk()->assertDontSee($post->body);
    }

    public function test_posts_are_ordered_by_published_at_descending(): void
    {
        $team = $this->team('Kronoloji Takımı');
        $olderPost = $this->makePost($team, 'Kronolojik eski gönderi', publishedAt: now()->subHours(2));
        $newerPost = $this->makePost($team, 'Kronolojik yeni gönderi', publishedAt: now()->subHour());

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder([$newerPost->body, $olderPost->body]);
    }

    public function test_posts_with_same_published_at_are_ordered_by_id_descending(): void
    {
        $team = $this->team('Eşit Zaman Takımı');
        $publishedAt = now()->subMinute();
        $lowerIdPost = $this->makePost($team, 'Aynı zamanda düşük kimlik', publishedAt: $publishedAt);
        $higherIdPost = $this->makePost($team, 'Aynı zamanda yüksek kimlik', publishedAt: $publishedAt);

        $this->assertGreaterThan($lowerIdPost->id, $higherIdPost->id);
        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder([$higherIdPost->body, $lowerIdPost->body]);
    }

    private function team(string $name, TeamStatus $status = TeamStatus::Active): Team
    {
        return Team::create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'short_name' => str($name)->limit(8, '')->upper()->toString(),
            'primary_color' => '#172554',
            'secondary_color' => '#ffffff',
            'status' => $status,
        ]);
    }

    private function makePost(
        Team $team,
        string $body,
        PostStatus $status = PostStatus::Published,
        mixed $publishedAt = false,
    ): Post {
        return Post::create([
            'team_id' => $team->id,
            'created_by' => $this->author->id,
            'type' => PostType::Text,
            'body' => $body,
            'status' => $status,
            'published_at' => $publishedAt === false ? now()->subMinute() : $publishedAt,
        ]);
    }
}
