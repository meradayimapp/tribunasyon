<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\TeamStatus;
use App\Enums\UserRole;
use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use App\Services\SeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeoInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    private int $providerSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://tribunasyon.com', 'app.name' => 'Tribünasyon']);
        Storage::fake('public');
        Carbon::setTestNow('2026-09-11 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_published_post_is_indexable_and_uses_manual_metadata_canonical_and_cover_image(): void
    {
        $post = $this->createPost($this->team(), [
            'seo_title' => 'Özel SEO Başlığı',
            'seo_description' => 'Özel SEO açıklaması.',
        ]);
        $post->media()->create(['type' => 'image', 'path' => 'posts/cover.jpg', 'sort_order' => 1]);
        $post->media()->create(['type' => 'image', 'path' => 'posts/second.jpg', 'sort_order' => 2]);

        $canonical = "https://tribunasyon.com/takim/{$post->team->slug}/gonderi/{$post->id}";

        $this->get(route('posts.show', [$post->team, $post]))
            ->assertOk()
            ->assertSee('<title>Özel SEO Başlığı</title>', false)
            ->assertSee('<meta name="description" content="Özel SEO açıklaması.">', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<link rel="canonical" href="'.$canonical.'">', false)
            ->assertSee('<meta property="og:image" content="https://tribunasyon.com/storage/posts/cover.jpg">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('"@type":"SocialMediaPosting"', false);
    }

    public function test_draft_and_future_post_are_noindex_and_not_publicly_exposed(): void
    {
        $team = $this->team();
        $draft = $this->createPost($team, ['status' => PostStatus::Draft, 'published_at' => null]);
        $future = $this->createPost($team, ['published_at' => now()->addDay()]);

        foreach ([$draft, $future] as $post) {
            $post->load(['team', 'media']);
            $this->assertSame('noindex, nofollow', app(SeoService::class)->post($post)->robots);
            $this->get(route('posts.show', [$team, $post]))->assertNotFound();
        }
    }

    public function test_published_content_of_an_inactive_team_is_noindex(): void
    {
        $team = $this->team(['status' => TeamStatus::Inactive]);
        $post = $this->createPost($team);

        $this->get(route('posts.show', [$team, $post]))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_team_pages_have_distinct_metadata_cover_priority_and_structured_data(): void
    {
        $team = $this->team([
            'name' => 'Fenerbahçe',
            'slug' => 'fenerbahce',
            'logo' => 'teams/logos/fenerbahce.png',
            'cover_image' => 'teams/covers/fenerbahce.jpg',
        ]);

        $this->get(route('teams.show', $team))
            ->assertOk()
            ->assertSee('<title>Fenerbahçe Topluluğu, Paylaşımlar ve Maçlar | Tribünasyon</title>', false)
            ->assertSee('<link rel="canonical" href="https://tribunasyon.com/takim/fenerbahce">', false)
            ->assertSee('<meta property="og:image" content="https://tribunasyon.com/storage/teams/covers/fenerbahce.jpg">', false)
            ->assertSee('"@type":"SportsTeam"', false);

        $this->get(route('teams.fixtures', $team))
            ->assertOk()
            ->assertSee('<title>Fenerbahçe Fikstürü ve Maçları | Tribünasyon</title>', false);

        $this->get(route('teams.players', $team))
            ->assertOk()
            ->assertSee('<title>Fenerbahçe Oyuncuları ve Kadrosu | Tribünasyon</title>', false);
    }

    public function test_match_page_has_dynamic_metadata_and_only_database_backed_event_data(): void
    {
        $competition = $this->competition();
        $home = $this->footballTeam('Fenerbahçe', ['provider_logo_url' => 'https://img.example/home.png']);
        $away = $this->footballTeam('Galatasaray');
        $match = $this->match($competition, $home, $away, ['venue_name' => 'Test Stadı']);

        $this->get(route('matches.show', $match))
            ->assertOk()
            ->assertSee('<title>Fenerbahçe - Galatasaray Maçı | Tribünasyon</title>', false)
            ->assertSee('<link rel="canonical" href="https://tribunasyon.com/maclar/'.$match->id.'">', false)
            ->assertSee('<meta property="og:image" content="https://img.example/home.png">', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('"@type":"SportsEvent"', false)
            ->assertSee('"name":"Test Stadı"', false)
            ->assertDontSee('eventAttendanceMode', false);
    }

    public function test_sitemap_contains_only_indexable_team_post_and_match_urls(): void
    {
        $active = $this->team(['slug' => 'aktif']);
        $inactive = $this->team(['slug' => 'pasif', 'status' => TeamStatus::Inactive]);
        $published = $this->createPost($active);
        $draft = $this->createPost($active, ['status' => PostStatus::Draft, 'published_at' => null]);
        $future = $this->createPost($active, ['published_at' => now()->addDay()]);
        $inactivePost = $this->createPost($inactive);

        $competition = $this->competition();
        $home = $this->footballTeam('Ev');
        $away = $this->footballTeam('Deplasman');
        $match = $this->match($competition, $home, $away);
        $passiveFootballTeam = $this->footballTeam('Pasif', ['is_active' => false]);
        $excludedMatch = $this->match($competition, $passiveFootballTeam, $away);

        $response = $this->get(route('seo.sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = $response->streamedContent();

        $this->assertStringContainsString('<loc>https://tribunasyon.com/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://tribunasyon.com/takim/aktif</loc>', $xml);
        $this->assertStringContainsString('<loc>https://tribunasyon.com/takim/aktif/fikstur</loc>', $xml);
        $this->assertStringContainsString('<loc>https://tribunasyon.com/takim/aktif/oyuncular</loc>', $xml);
        $this->assertStringContainsString("<loc>https://tribunasyon.com/takim/aktif/gonderi/{$published->id}</loc>", $xml);
        $this->assertStringContainsString("<loc>https://tribunasyon.com/maclar/{$match->id}</loc>", $xml);
        $this->assertStringNotContainsString('/takim/pasif', $xml);
        $this->assertStringNotContainsString("/gonderi/{$draft->id}</loc>", $xml);
        $this->assertStringNotContainsString("/gonderi/{$future->id}</loc>", $xml);
        $this->assertStringNotContainsString("/gonderi/{$inactivePost->id}</loc>", $xml);
        $this->assertStringNotContainsString("/maclar/{$excludedMatch->id}</loc>", $xml);
    }

    public function test_admin_and_private_pages_are_noindex(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->actingAs($admin)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_moderator_and_admin_can_save_optional_post_seo_overrides(): void
    {
        $team = $this->team();
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $moderator->moderatedTeams()->attach($team);

        $this->actingAs($moderator)->post(route('moderator.posts.store'), [
            'team_id' => $team->id,
            'body' => 'SEO alanları olan gönderi',
            'status' => PostStatus::Published->value,
            'seo_title' => 'Moderatör başlığı',
            'seo_description' => 'Moderatör açıklaması',
        ])->assertRedirect(route('moderator.posts.index'));

        $post = Post::latest('id')->firstOrFail();
        $this->assertSame('Moderatör başlığı', $post->seo_title);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->put(route('admin.posts.update', $post), [
            'seo_title' => 'Admin başlığı',
            'seo_description' => 'Admin açıklaması',
        ])->assertRedirect(route('admin.posts.index'));

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'seo_title' => 'Admin başlığı',
            'seo_description' => 'Admin açıklaması',
        ]);
    }

    public function test_robots_output_blocks_management_and_private_areas(): void
    {
        $this->get(route('seo.robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin/', false)
            ->assertSee('Disallow: /moderator/', false)
            ->assertSee('Disallow: /profil-duzenle', false)
            ->assertSee('Sitemap: https://tribunasyon.com/sitemap.xml', false);
    }

    private function team(array $attributes = []): Team
    {
        $slug = $attributes['slug'] ?? 'deneme-takimi';

        return Team::create(array_merge([
            'name' => 'Deneme Takımı',
            'slug' => $slug,
            'short_name' => 'DNT',
            'primary_color' => '#111111',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
        ], $attributes));
    }

    private function createPost(Team $team, array $attributes = []): Post
    {
        return Post::create(array_merge([
            'team_id' => $team->id,
            'created_by' => User::factory()->create()->id,
            'type' => PostType::Text,
            'body' => 'Takımımızın bugünkü önemli gelişmeleri ve taraftar haberleri.',
            'status' => PostStatus::Published,
            'published_at' => now()->subMinute(),
        ], $attributes));
    }

    private function competition(array $attributes = []): FootballCompetition
    {
        $this->providerSequence++;

        return FootballCompetition::create(array_merge([
            'provider' => 'test',
            'provider_league_id' => 'league-'.$this->providerSequence,
            'name' => 'Süper Lig',
            'display_name' => 'Süper Lig',
            'slug' => 'super-lig-'.$this->providerSequence,
            'is_active' => true,
        ], $attributes));
    }

    private function footballTeam(string $name, array $attributes = []): FootballTeam
    {
        $this->providerSequence++;

        return FootballTeam::create(array_merge([
            'provider' => 'test',
            'provider_team_id' => 'team-'.$this->providerSequence,
            'provider_name' => $name,
            'display_name' => $name,
            'is_active' => true,
        ], $attributes));
    }

    private function match(FootballCompetition $competition, FootballTeam $home, FootballTeam $away, array $attributes = []): FootballMatch
    {
        $this->providerSequence++;

        return FootballMatch::create(array_merge([
            'competition_id' => $competition->id,
            'home_football_team_id' => $home->id,
            'away_football_team_id' => $away->id,
            'provider' => 'test',
            'provider_match_id' => 'match-'.$this->providerSequence,
            'kickoff_at' => '2026-09-12 17:00:00',
            'status' => 'scheduled',
            'status_display' => 'Başlamadı',
            'is_live' => false,
        ], $attributes));
    }
}
