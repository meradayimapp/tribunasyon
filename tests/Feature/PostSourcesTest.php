<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\TeamStatus;
use App\Enums\UserRole;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostSourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_post_with_sources(): void
    {
        [$team] = $this->moderatedTeam();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.posts.create'))
            ->assertOk()->assertSee('postSourcesEditor', false);

        $this->actingAs($admin)->post(route('admin.posts.store'), [
            'team_id' => $team->id,
            'body' => 'Admin kaynaklı haber',
            'status' => 'published',
            'sources_editor_present' => '1',
            'sources' => [['label' => 'TRT Spor', 'url' => 'https://www.trtspor.com.tr/haber']],
        ])->assertRedirect(route('admin.posts.index'));

        $post = Post::latest('id')->firstOrFail();
        $this->assertSame($admin->id, $post->created_by);
        $this->assertSame('Admin kaynaklı haber', $post->body);
        $this->assertSame('TRT Spor', $post->sources->sole()->label);
    }

    public function test_admin_can_add_multiple_sources_and_edit_form_shows_them(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $post = $this->createPost($team, $moderator);

        $this->actingAs($admin)->put(route('admin.posts.update', $post), [
            'seo_title' => 'Kaynaklı haber',
            'sources_editor_present' => '1',
            'sources' => [
                ['label' => 'TRT Spor', 'url' => 'https://www.trtspor.com.tr/haber'],
                ['label' => '', 'url' => 'https://www.fanatik.com.tr/haber'],
            ],
        ])->assertRedirect(route('admin.posts.index'));

        $this->assertSame('Kaynaklı haber', $post->fresh()->seo_title);
        $this->assertSame(['TRT Spor', null], $post->fresh()->sources->pluck('label')->all());
        $this->assertSame([1, 2], $post->fresh()->sources->pluck('sort_order')->all());
        $this->actingAs($admin)->get(route('admin.posts.edit', $post))
            ->assertOk()->assertSee('postSourcesEditor', false)->assertSee('trtspor.com.tr');
    }

    public function test_moderator_can_create_edit_reorder_and_remove_sources_without_changing_post_media(): void
    {
        [$team, $moderator] = $this->moderatedTeam();

        $this->actingAs($moderator)->post(route('moderator.posts.store'), [
            'team_id' => $team->id,
            'body' => 'Kaynaklı gönderi',
            'status' => 'published',
            'sources_editor_present' => '1',
            'sources' => [
                ['label' => 'Birinci', 'url' => 'https://example.com/one'],
                ['label' => 'İkinci', 'url' => 'https://example.net/two'],
            ],
        ])->assertRedirect(route('moderator.posts.index'));

        $post = Post::latest('id')->firstOrFail();
        $this->assertSame(PostType::Text, $post->type);
        $this->assertSame(['Birinci', 'İkinci'], $post->sources->pluck('label')->all());
        $this->actingAs($moderator)->get(route('moderator.posts.edit', $post))
            ->assertOk()->assertSee('example.com');

        $this->actingAs($moderator)->put(route('moderator.posts.update', $post), [
            'team_id' => $team->id,
            'body' => 'Kaynaklı gönderi',
            'status' => 'published',
            'sources_editor_present' => '1',
            'sources' => [
                ['label' => 'Güncel ikinci', 'url' => 'https://example.net/two'],
                ['label' => 'Yeni', 'url' => 'https://example.org/new'],
            ],
        ])->assertRedirect(route('moderator.posts.index'));

        $this->assertSame(['Güncel ikinci', 'Yeni'], $post->fresh()->sources->pluck('label')->all());
        $this->assertSame([1, 2], $post->fresh()->sources->pluck('sort_order')->all());
        $this->assertSame('Kaynaklı gönderi', $post->fresh()->body);

        $this->actingAs($moderator)->put(route('moderator.posts.update', $post), [
            'team_id' => $team->id,
            'body' => 'Kaynaklı gönderi',
            'status' => 'published',
            'sources_editor_present' => '1',
        ])->assertRedirect(route('moderator.posts.index'));

        $this->assertCount(0, $post->fresh()->sources);
    }

    public function test_unauthorized_moderator_cannot_change_another_teams_sources(): void
    {
        [$team, $owner] = $this->moderatedTeam();
        [, $otherModerator] = $this->moderatedTeam('Diğer', 'diger');
        $post = $this->createPost($team, $owner);
        $source = $post->sources()->create(['url' => 'https://example.com/original', 'sort_order' => 1]);

        $this->actingAs($otherModerator)->put(route('moderator.posts.update', $post), [
            'team_id' => $team->id,
            'body' => 'Değiştirildi',
            'status' => 'published',
            'sources_editor_present' => '1',
            'sources' => [['url' => 'https://example.org/foreign']],
        ])->assertForbidden();

        $this->assertDatabaseHas('post_sources', ['id' => $source->id, 'url' => 'https://example.com/original']);
        $this->assertSame('Kaynak testi', $post->fresh()->body);
    }

    public function test_invalid_and_unsafe_urls_are_rejected(): void
    {
        [$team, $moderator] = $this->moderatedTeam();

        foreach (['not-a-url', 'javascript:alert(1)', 'data:text/html,hello', 'file:///tmp/test', 'https://user:pass@example.com/path'] as $url) {
            $this->actingAs($moderator)->post(route('moderator.posts.store'), [
                'team_id' => $team->id,
                'body' => 'Geçersiz kaynak',
                'status' => 'published',
                'sources' => [['url' => $url]],
            ])->assertSessionHasErrors('sources.0.url');
        }

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('post_sources', 0);
    }

    public function test_duplicate_urls_are_normalized_and_empty_rows_ignored(): void
    {
        [$team, $moderator] = $this->moderatedTeam();

        $this->actingAs($moderator)->post(route('moderator.posts.store'), [
            'team_id' => $team->id,
            'body' => 'Tekil kaynak',
            'status' => 'published',
            'sources' => [
                ['url' => ' HTTPS://EXAMPLE.COM/ ', 'label' => 'İlk'],
                ['url' => 'https://example.com', 'label' => 'Tekrar'],
                ['url' => '', 'label' => ''],
            ],
        ])->assertRedirect(route('moderator.posts.index'));

        $sources = Post::latest('id')->firstOrFail()->sources;
        $this->assertCount(1, $sources);
        $this->assertSame('https://example.com', $sources->first()->url);
        $this->assertSame('İlk', $sources->first()->label);
    }

    public function test_source_limit_and_label_without_url_are_rejected(): void
    {
        [$team, $moderator] = $this->moderatedTeam();

        $this->actingAs($moderator)->post(route('moderator.posts.store'), [
            'team_id' => $team->id, 'body' => 'Çok kaynak', 'status' => 'published',
            'sources' => array_map(fn (int $number): array => ['url' => "https://example.com/{$number}"], range(1, 11)),
        ])->assertSessionHasErrors('sources');

        $this->actingAs($moderator)->post(route('moderator.posts.store'), [
            'team_id' => $team->id, 'body' => 'Eksik URL', 'status' => 'published',
            'sources' => [['label' => 'İsimsiz bağlantı', 'url' => '']],
        ])->assertSessionHasErrors('sources.0.url');
    }

    public function test_legacy_update_without_source_editor_does_not_remove_existing_sources(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $post = $this->createPost($team, $moderator);
        $source = $post->sources()->create(['url' => 'https://example.com/story', 'sort_order' => 1]);

        $this->actingAs($admin)->put(route('admin.posts.update', $post), [
            'seo_title' => 'Yalnız SEO değişti',
        ])->assertRedirect(route('admin.posts.index'));

        $this->assertDatabaseHas('post_sources', ['id' => $source->id, 'url' => 'https://example.com/story']);
    }

    public function test_sources_are_absent_for_empty_posts_and_shared_across_feed_and_detail(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $post = $this->createPost($team, $moderator);

        $this->get(route('posts.show', [$team, $post]))->assertOk()->assertDontSee('post-sources-trigger', false)->assertDontSee('Kaynaklar');

        $post->sources()->create(['url' => 'https://www.fanatik.com.tr/haber', 'sort_order' => 1]);
        $this->get(route('posts.show', [$team, $post]))
            ->assertOk()->assertSee('post-sources-trigger', false)->assertSee('fanatik.com.tr')
            ->assertSee('target="_blank"', false)->assertSee('rel="noopener noreferrer nofollow external"', false);
        $this->get(route('home'))->assertOk()->assertSee('post-sources-trigger', false);
        $this->get(route('teams.show', $team))->assertOk()->assertSee('post-sources-trigger', false);

        $post->sources()->create(['url' => 'https://example.org/story', 'label' => 'Özel Kaynak', 'sort_order' => 2]);
        $detail = $this->get(route('posts.show', [$team, $post]))->assertOk()
            ->assertSee('Özel Kaynak')->assertSee('example.org');
        $this->assertSame(1, substr_count($detail->getContent(), 'class="post-sources-trigger"'));
        $this->assertSame(2, substr_count($detail->getContent(), 'class="post-sources-link-copy"'));
    }

    public function test_feed_eager_loads_sources_in_one_query(): void
    {
        [$team, $moderator] = $this->moderatedTeam();

        foreach (range(1, 8) as $index) {
            $post = $this->createPost($team, $moderator, "Kaynaklı akış {$index}");
            $post->sources()->create(['url' => "https://example.com/{$index}", 'sort_order' => 1]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('home'))->assertOk();
        $sourceQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => (bool) preg_match('/\bfrom\s+[`"]?post_sources[`"]?/i', $query['query']));
        DB::disableQueryLog();

        $this->assertCount(1, $sourceQueries);
    }

    public function test_source_label_is_escaped_in_public_markup(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $post = $this->createPost($team, $moderator);
        $post->sources()->create([
            'url' => 'https://example.com/story',
            'label' => '<script>alert(1)</script>',
            'sort_order' => 1,
        ]);

        $this->get(route('posts.show', [$team, $post]))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_soft_delete_preserves_sources_and_force_delete_cascades_them(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $post = $this->createPost($team, $moderator);
        $source = $post->sources()->create(['url' => 'https://example.com/story', 'sort_order' => 1]);

        $post->delete();
        $this->assertDatabaseHas('post_sources', ['id' => $source->id]);
        $post->restore();
        $this->assertCount(1, $post->fresh()->sources);
        $post->forceDelete();
        $this->assertDatabaseMissing('post_sources', ['id' => $source->id]);
    }

    private function moderatedTeam(string $name = 'Kaynak Takımı', string $slug = 'kaynak-takimi'): array
    {
        $team = Team::create([
            'name' => $name, 'slug' => $slug, 'short_name' => 'KYN',
            'primary_color' => '#172554', 'secondary_color' => '#ffffff', 'status' => TeamStatus::Active,
        ]);
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $moderator->moderatedTeams()->attach($team);

        return [$team, $moderator];
    }

    private function createPost(Team $team, User $moderator, string $body = 'Kaynak testi'): Post
    {
        return Post::create([
            'team_id' => $team->id, 'created_by' => $moderator->id, 'type' => PostType::Text,
            'body' => $body, 'status' => PostStatus::Published, 'published_at' => now()->subMinute(),
        ]);
    }
}
