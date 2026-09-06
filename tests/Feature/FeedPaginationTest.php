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

class FeedPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_layout_defaults_to_dark_theme_and_uses_akis_label(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-bs-theme="dark"', false)
            ->assertSee('tribun-theme', false)
            ->assertSee('Akış')
            ->assertDontSee('Ana Sayfa');
    }

    public function test_feed_can_open_second_page_with_bootstrap_pagination(): void
    {
        $team = Team::create([
            'name' => 'Sayfalama Takımı',
            'slug' => 'sayfalama-takimi',
            'short_name' => 'SAY',
            'primary_color' => '#111827',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
        ]);
        $moderator = User::factory()->create();

        foreach (range(1, 9) as $index) {
            Post::create([
                'team_id' => $team->id,
                'created_by' => $moderator->id,
                'type' => PostType::Text,
                'body' => "Sayfalama gönderisi {$index}",
                'status' => PostStatus::Published,
                'published_at' => now()->subMinutes(10 - $index),
            ]);
        }

        $firstPage = $this->get(route('home'))
            ->assertOk()
            ->assertSee('Sayfalama gönderisi 9')
            ->assertDontSee('Sayfalama gönderisi 1')
            ->assertSee('Sonraki ›')
            ->assertDontSee('Showing')
            ->assertSee('class="ui-icon"', false);

        $this->assertBootstrapPaginationWithoutSvg($firstPage->getContent());

        $secondPage = $this->get(route('home', ['page' => 2]))
            ->assertOk()
            ->assertSee('Sayfalama gönderisi 1')
            ->assertDontSee('Sayfalama gönderisi 9')
            ->assertSee('‹ Önceki')
            ->assertDontSee('Showing')
            ->assertSee('class="ui-icon"', false);

        $this->assertBootstrapPaginationWithoutSvg($secondPage->getContent());
    }

    private function assertBootstrapPaginationWithoutSvg(string $html): void
    {
        // The navigation now intentionally uses SVG. Keep this regression
        // focused on Bootstrap pagination, which must not use Tailwind SVGs.
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $pagination = '//div[contains(concat(" ", normalize-space(@class), " "), " feed-pagination ")]';

        $this->assertSame(1, $xpath->query($pagination)->length);
        $this->assertGreaterThan(0, $xpath->query($pagination.'//ul[contains(@class, "pagination")]')->length);
        $this->assertSame(0, $xpath->query($pagination.'//svg')->length);
        $this->assertGreaterThan(0, $xpath->query('//svg[contains(@class, "ui-icon")]')->length);
    }
}
