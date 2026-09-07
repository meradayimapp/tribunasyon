<?php

namespace Tests\Feature;

use App\Enums\TeamStatus;
use App\Livewire\HeaderSearch;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlayerPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_player_profile_shows_real_data_and_formatted_market_value(): void
    {
        $team = $this->team();
        $player = Player::factory()->create([
            'current_team_id' => $team->id,
            'market_value_amount' => 42_500_000,
            'market_value_currency' => 'EUR',
            'bio' => '<script>alert(1)</script>',
        ]);

        $this->get(route('players.show', $player))
            ->assertOk()
            ->assertSee($player->name)
            ->assertSee($team->name)
            ->assertSee('€42.5M')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_inactive_and_soft_deleted_players_are_hidden_publicly(): void
    {
        $inactive = Player::factory()->inactive()->create();
        $deleted = Player::factory()->create();
        $deleted->delete();

        $this->get(route('players.show', $inactive))->assertNotFound();
        $this->get('/oyuncu/'.$deleted->slug)->assertNotFound();
        $this->get(route('players.index'))->assertDontSee($inactive->name)->assertDontSee($deleted->name);
    }

    public function test_player_directory_filters_by_player_and_team_name(): void
    {
        $team = $this->team(['name' => 'Aranan Kulüp', 'slug' => 'aranan-kulup']);
        $player = Player::factory()->create(['name' => 'Bulunan Oyuncu', 'slug' => 'bulunan-oyuncu', 'current_team_id' => $team->id]);
        Player::factory()->create(['name' => 'Başka İsim']);

        $this->get(route('players.index', ['q' => 'Aranan']))
            ->assertOk()->assertSee($player->name)->assertDontSee('Başka İsim');
    }

    public function test_global_search_returns_only_active_players(): void
    {
        $active = Player::factory()->create(['name' => 'Arama Yıldızı', 'slug' => 'arama-yildizi']);
        $inactive = Player::factory()->inactive()->create(['name' => 'Arama Pasif', 'slug' => 'arama-pasif']);

        $this->get(route('search.index', ['q' => 'Arama']))
            ->assertOk()->assertSee($active->name)->assertDontSee($inactive->name);
    }

    public function test_header_search_requires_three_characters_and_limits_players(): void
    {
        Player::factory()->count(5)->create(['name' => 'Test Oyuncu']);

        Livewire::test(HeaderSearch::class)->set('query', 'Te')->assertDontSee('Test Oyuncu');
        $component = Livewire::test(HeaderSearch::class)->set('query', 'Test')->assertSee('Test Oyuncu');
        $this->assertLessThanOrEqual(3, substr_count($component->html(), 'search-player-avatar'));
    }

    public function test_team_hard_delete_nulls_current_team(): void
    {
        $team = $this->team();
        $player = Player::factory()->create(['current_team_id' => $team->id]);
        $team->forceDelete();

        $this->assertNull($player->fresh()->current_team_id);
    }

    public function test_market_value_formats_integer_thousands_without_losing_zeroes(): void
    {
        $player = Player::factory()->make(['market_value_amount' => 950_000, 'market_value_currency' => 'EUR']);

        $this->assertSame('€950K', $player->formatted_market_value);
    }

    private function team(array $attributes = []): Team
    {
        return Team::create(array_merge([
            'name' => 'Deneme Takımı', 'slug' => 'deneme-takimi', 'short_name' => 'DNT',
            'primary_color' => '#111111', 'secondary_color' => '#ffffff', 'status' => TeamStatus::Active,
        ], $attributes));
    }
}
