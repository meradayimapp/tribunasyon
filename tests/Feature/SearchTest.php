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

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_can_be_found_by_name(): void
    {
        $team = $this->team(['name' => 'Fenerbahçe', 'slug' => 'fenerbahce', 'short_name' => 'FB']);

        $this->get(route('search.index', ['q' => 'Fener']))
            ->assertOk()
            ->assertSee($team->name)
            ->assertSee('team-logo-search', false)
            ->assertSee('search-team-card-copy', false);
    }

    public function test_published_post_can_be_found_by_body(): void
    {
        $team = $this->team();
        $post = $this->createPost($team, PostStatus::Published, 'Transfer görüşmeleri bu hafta tamamlanacak.');

        $this->get(route('search.index', ['q' => 'transfer']))
            ->assertOk()
            ->assertSee($post->body);
    }

    public function test_draft_post_is_not_in_search_results(): void
    {
        $team = $this->team();
        $post = $this->createPost($team, PostStatus::Draft, 'Taslak transfer duyurusu');

        $this->get(route('search.index', ['q' => 'transfer']))
            ->assertOk()
            ->assertDontSee($post->body);
    }

    public function test_archived_post_is_not_in_search_results(): void
    {
        $team = $this->team();
        $post = $this->createPost($team, PostStatus::Archived, 'Arşivlenen transfer duyurusu');

        $this->get(route('search.index', ['q' => 'transfer']))
            ->assertOk()
            ->assertDontSee($post->body);
    }

    public function test_creator_identity_is_not_exposed_in_search_results(): void
    {
        $team = $this->team();
        $moderator = User::factory()->create([
            'name' => 'Arama Gizli Moderatör',
            'username' => 'arama.gizli',
            'email' => 'arama-gizli@example.test',
        ]);
        $post = Post::create([
            'team_id' => $team->id,
            'created_by' => $moderator->id,
            'type' => PostType::Text,
            'body' => 'Avrupa maçı hazırlıkları tamamlandı.',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);

        $this->get(route('search.index', ['q' => 'Avrupa']))
            ->assertOk()
            ->assertSee($post->body)
            ->assertDontSee($moderator->name)
            ->assertDontSee($moderator->username)
            ->assertDontSee($moderator->email);
    }

    public function test_empty_query_is_safe(): void
    {
        $this->get(route('search.index'))
            ->assertOk()
            ->assertSee('Takım adı veya gönderi metni yazarak aramaya başlayın.');
    }

    public function test_query_longer_than_limit_is_rejected(): void
    {
        $this->from(route('home'))
            ->get(route('search.index', ['q' => str_repeat('a', 101)]))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('q');
    }

    private function team(array $attributes = []): Team
    {
        return Team::create(array_merge([
            'name' => 'Deneme Takımı',
            'slug' => 'deneme-takimi',
            'short_name' => 'DNT',
            'primary_color' => '#15203a',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
        ], $attributes));
    }

    private function createPost(Team $team, PostStatus $status, string $body): Post
    {
        $moderator = User::factory()->create();

        return Post::create([
            'team_id' => $team->id,
            'created_by' => $moderator->id,
            'type' => PostType::Text,
            'body' => $body,
            'status' => $status,
            'published_at' => now(),
        ]);
    }
}
