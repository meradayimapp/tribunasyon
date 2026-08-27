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

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Sayfalama gönderisi 9')
            ->assertDontSee('Sayfalama gönderisi 1')
            ->assertSee('Sonraki ›')
            ->assertDontSee('Showing')
            ->assertDontSee('<svg', false);

        $this->get(route('home', ['page' => 2]))
            ->assertOk()
            ->assertSee('Sayfalama gönderisi 1')
            ->assertDontSee('Sayfalama gönderisi 9')
            ->assertSee('‹ Önceki')
            ->assertDontSee('Showing')
            ->assertDontSee('<svg', false);
    }
}
