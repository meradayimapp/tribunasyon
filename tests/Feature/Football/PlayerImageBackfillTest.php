<?php

namespace Tests\Feature\Football;

use App\Enums\TeamStatus;
use App\Models\FootballTeam;
use App\Models\Player;
use App\Models\Team;
use App\Services\Football\LiveFootballApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlayerImageBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.live_football_api', [
            'key' => 'backfill-only-secret',
            'base_url' => 'https://football.test/api/v1',
        ]);
    }

    public function test_backfill_uses_one_team_squad_call_and_updates_only_existing_provider_ids(): void
    {
        $team = $this->linkedTeam('team-1');
        $mapped = Player::factory()->create([
            'name' => 'Yerel Ad Farklı', 'current_team_id' => $team->id,
            'provider_player_id' => 'player-1', 'provider_image_url' => null,
            'photo_path' => 'players/photos/manual.png',
        ]);
        $unmapped = Player::factory()->create([
            'name' => 'API Oyuncusu', 'current_team_id' => $team->id,
            'provider_player_id' => null, 'provider_image_url' => null,
        ]);
        $other = Player::factory()->create([
            'current_team_id' => $team->id, 'provider_player_id' => 'not-in-squad',
        ]);
        Http::fake(['football.test/api/v1/team_squad*' => Http::response($this->response('team-1', [
            $this->apiPlayer('player-1', 'https://cdn.test/one.png'),
            $this->apiPlayer('player-2', 'https://cdn.test/two.png'),
        ]))]);

        $this->artisan('football:sync-player-images')->assertExitCode(0);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/team_squad')
            && $request->data()['team_id'] === 'team-1');
        $this->assertSame('https://cdn.test/one.png', $mapped->fresh()->provider_image_url);
        $this->assertSame('players/photos/manual.png', $mapped->fresh()->photo_path);
        $this->assertNull($unmapped->fresh()->provider_player_id);
        $this->assertNull($unmapped->fresh()->provider_image_url);
        $this->assertNull($other->fresh()->provider_image_url);
        $this->assertDatabaseCount('players', 3);

        $this->artisan('football:sync-player-images')->assertExitCode(0);
        Http::assertSentCount(2); // Only the still-missing, provider-mapped player remains eligible.
        $this->assertDatabaseCount('players', 3);
    }

    public function test_backfill_skips_existing_image_and_rejects_unsafe_or_empty_image_without_clearing_data(): void
    {
        $team = $this->linkedTeam('team-1');
        $existing = Player::factory()->create([
            'current_team_id' => $team->id, 'provider_player_id' => 'player-1',
            'provider_image_url' => 'https://cdn.test/existing.png',
        ]);
        $missing = Player::factory()->create([
            'current_team_id' => $team->id, 'provider_player_id' => 'player-2',
        ]);
        Http::fake(['*' => Http::response($this->response('team-1', [
            $this->apiPlayer('player-1', 'https://cdn.test/replacement.png'),
            $this->apiPlayer('player-2', 'javascript:alert(1)'),
        ]))]);

        $this->artisan('football:sync-player-images')->assertExitCode(0);

        $this->assertSame('https://cdn.test/existing.png', $existing->fresh()->provider_image_url);
        $this->assertNull($missing->fresh()->provider_image_url);
        $this->assertDatabaseCount('players', 2);
    }

    public function test_team_option_limits_calls_and_api_failure_does_not_mutate_players_or_expose_key(): void
    {
        $first = $this->linkedTeam('team-1');
        $second = $this->linkedTeam('team-2');
        $player = Player::factory()->create([
            'current_team_id' => $first->id, 'provider_player_id' => 'player-1',
        ]);
        Player::factory()->create([
            'current_team_id' => $second->id, 'provider_player_id' => 'player-2',
        ]);
        Http::fake(['*' => Http::response(['success' => false], 503)]);

        $this->artisan('football:sync-player-images', ['--team' => $first->id])
            ->doesntExpectOutput('backfill-only-secret')
            ->assertExitCode(1);

        Http::assertSentCount(1);
        $this->assertNull($player->fresh()->provider_image_url);
        $this->assertDatabaseCount('players', 2);
    }

    private function linkedTeam(string $providerTeamId): Team
    {
        $team = Team::create([
            'name' => $providerTeamId, 'slug' => $providerTeamId,
            'short_name' => 'TKM', 'primary_color' => '#111111',
            'secondary_color' => '#ffffff', 'status' => TeamStatus::Active,
        ]);
        FootballTeam::create([
            'provider' => LiveFootballApiService::PROVIDER,
            'provider_team_id' => $providerTeamId,
            'provider_name' => $providerTeamId,
            'is_active' => true,
            'team_id' => $team->id,
        ]);

        return $team;
    }

    private function response(string $teamId, array $squad): array
    {
        return ['success' => true, 'data' => ['team_id' => $teamId, 'squad' => $squad]];
    }

    private function apiPlayer(string $id, ?string $image): array
    {
        return ['id' => $id, 'name' => 'API Oyuncusu', 'image' => $image];
    }
}
