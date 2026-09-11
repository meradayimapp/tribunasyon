<?php

namespace Tests\Feature\Admin;

use App\Enums\PlayerStatus;
use App\Enums\TeamStatus;
use App\Enums\UserRole;
use App\Models\FootballTeam;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Services\Football\LiveFootballApiService;
use App\Services\Football\PlayerSquadSyncService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class PlayerSquadImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.live_football_api', [
            'key' => 'fixture-only-secret',
            'base_url' => 'https://football.test/api/v1',
        ]);
    }

    public function test_only_admin_can_access_import_flow(): void
    {
        $member = User::factory()->create();

        $this->get(route('admin.players.import.create'))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('admin.players.import.create'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.players.import.preview'), ['team_id' => 1])->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.players.import.create'))->assertOk();
    }

    public function test_linked_team_preview_uses_one_team_squad_call_and_writes_nothing(): void
    {
        $admin = $this->admin();
        [$team, $footballTeam] = $this->linkedTeam();
        Http::fake(['football.test/api/v1/team_squad*' => Http::response($this->squadResponse())]);

        $token = $this->previewToken($admin, $team);

        $this->assertDatabaseCount('players', 0);
        $this->actingAs($admin)->get(route('admin.players.import.create', ['team_id' => $team->id, 'token' => $token]))
            ->assertOk()
            ->assertSee('API Oyuncusu')
            ->assertSee('Veritabanı henüz değiştirilmedi');
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/team_squad')
            && ! str_contains($request->url(), '/player?')
            && $request->data()['team_id'] === $footballTeam->provider_team_id);
    }

    public function test_unlinked_team_does_not_call_api(): void
    {
        $admin = $this->admin();
        $team = $this->team();
        Http::fake();

        $this->actingAs($admin)->post(route('admin.players.import.preview'), ['team_id' => $team->id])
            ->assertSessionHasErrors('team_id');

        Http::assertNothingSent();
        $this->assertDatabaseCount('players', 0);
    }

    public function test_invalid_empty_and_duplicate_provider_squads_are_rejected(): void
    {
        $admin = $this->admin();
        [$team] = $this->linkedTeam();

        foreach ([
            $this->squadResponse([]),
            $this->squadResponse([['name' => 'Kimliksiz']]),
            $this->squadResponse([
                $this->apiPlayer(),
                $this->apiPlayer(['name' => 'Tekrarlı']),
            ]),
        ] as $response) {
            Http::fake(['*' => Http::response($response)]);
            $this->actingAs($admin)->post(route('admin.players.import.preview'), ['team_id' => $team->id])
                ->assertSessionHasErrors('team_id');
            $this->assertDatabaseCount('players', 0);
        }
    }

    public function test_new_player_is_imported_with_provider_fields_and_unique_slug(): void
    {
        $admin = $this->admin();
        [$team] = $this->linkedTeam();
        Player::factory()->create(['name' => 'API Oyuncusu', 'slug' => 'api-oyuncusu']);
        Http::fake(['*' => Http::response($this->squadResponse())]);
        $token = $this->previewToken($admin, $team);

        $response = $this->apply($admin, $team, $token, [
            $this->choice('player-1', 'create'),
        ]);

        $response->assertRedirect(route('admin.players.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('players', [
            'provider_player_id' => 'player-1',
            'name' => 'API Oyuncusu',
            'slug' => 'api-oyuncusu-2',
            'current_team_id' => $team->id,
            'position' => 'Orta saha',
            'shirt_number' => 9,
            'nationality' => 'Türkiye',
        ]);
        $imported = Player::where('provider_player_id', 'player-1')->firstOrFail();
        $this->assertNotNull($imported->provider_last_synced_at);
        $this->assertNull($imported->birth_date);
        $this->assertNull($imported->market_value_amount);
        $this->assertNull($imported->photo_path);
        $this->assertNull($imported->cover_image_path);
        $this->assertNull($imported->bio);
    }

    public function test_repeated_import_updates_same_provider_player_without_duplicate(): void
    {
        $admin = $this->admin();
        [$team] = $this->linkedTeam();
        Http::fake(['*' => Http::response($this->squadResponse())]);

        $firstToken = $this->previewToken($admin, $team);
        $this->apply($admin, $team, $firstToken, [$this->choice('player-1', 'create')]);
        $secondToken = $this->previewToken($admin, $team);
        $this->apply($admin, $team, $secondToken, [$this->choice('player-1', 'sync')]);

        $this->assertDatabaseCount('players', 1);
        $this->assertSame('player-1', Player::firstOrFail()->provider_player_id);
    }

    public function test_positions_are_localized_except_attacker_which_is_left_for_manual_edit(): void
    {
        $admin = $this->admin();
        [$team] = $this->linkedTeam();
        Http::fake(['*' => Http::response($this->squadResponse([
            $this->apiPlayer(['id' => 'goalkeeper-1', 'name' => 'Kaleci Oyuncu', 'position' => 'Goalkeeper']),
            $this->apiPlayer(['id' => 'defender-1', 'name' => 'Defans Oyuncusu', 'position' => 'Defender']),
            $this->apiPlayer(['id' => 'midfielder-1', 'name' => 'Orta Saha Oyuncusu', 'position' => 'Midfielder']),
            $this->apiPlayer(['id' => 'attacker-1', 'name' => 'Hücum Oyuncusu', 'position' => 'Attacker']),
        ]))]);

        $preview = app(PlayerSquadSyncService::class)->createPreview($admin, $team);
        $players = collect($preview['groups']['new'])->keyBy('provider_player_id');

        $this->assertSame('Kaleci', $players['goalkeeper-1']['position']);
        $this->assertSame('Defans', $players['defender-1']['position']);
        $this->assertSame('Orta saha', $players['midfielder-1']['position']);
        $this->assertNull($players['attacker-1']['position']);
        $this->assertTrue($players['attacker-1']['position_requires_manual_edit']);

        $this->actingAs($admin)
            ->get(route('admin.players.import.create', ['team_id' => $team->id, 'token' => $preview['token']]))
            ->assertOk()
            ->assertSee('Manuel düzenlenecek');
    }

    public function test_attacker_sync_preserves_a_manually_entered_position(): void
    {
        $admin = $this->admin();
        [$team] = $this->linkedTeam();
        $player = Player::factory()->create([
            'provider_player_id' => 'player-1',
            'current_team_id' => $team->id,
            'position' => 'Santrafor',
        ]);
        Http::fake(['*' => Http::response($this->squadResponse([
            $this->apiPlayer(['position' => 'Attacker']),
        ]))]);

        $token = $this->previewToken($admin, $team);
        $this->apply($admin, $team, $token, [$this->choice('player-1', 'sync')]);

        $this->assertSame('Santrafor', $player->fresh()->position);
    }

    public function test_existing_player_sync_preserves_manual_fields_relationships_slug_and_national_team(): void
    {
        $admin = $this->admin();
        [$oldTeam] = $this->linkedTeam('old-team', 'Eski Takım');
        [$newTeam] = $this->linkedTeam('new-team', 'Yeni Takım');
        $player = Player::factory()->create([
            'provider_player_id' => 'player-1',
            'name' => 'Eski Ad',
            'slug' => 'kalici-slug',
            'current_team_id' => $oldTeam->id,
            'photo_path' => 'players/photos/manual.jpg',
            'cover_image_path' => 'players/covers/manual.jpg',
            'bio' => 'Manuel biyografi',
            'national_team_name' => 'Türkiye Milli Takımı',
            'national_team_code' => 'TR',
            'birth_date' => '1999-01-01',
            'market_value_amount' => 10_000_000,
            'market_value_currency' => 'EUR',
        ]);
        $follower = User::factory()->create();
        $follower->followedPlayers()->attach($player);
        $message = $player->chatMessages()->create(['user_id' => $follower->id, 'body' => 'Korunacak sohbet']);
        Http::fake(['*' => Http::response($this->squadResponse(teamId: 'new-team'))]);

        $token = $this->previewToken($admin, $newTeam);
        $this->apply($admin, $newTeam, $token, [$this->choice('player-1', 'sync')]);
        $player->refresh();

        $this->assertSame('API Oyuncusu', $player->name);
        $this->assertSame('kalici-slug', $player->slug);
        $this->assertSame($newTeam->id, $player->current_team_id);
        $this->assertSame('players/photos/manual.jpg', $player->photo_path);
        $this->assertSame('players/covers/manual.jpg', $player->cover_image_path);
        $this->assertSame('Manuel biyografi', $player->bio);
        $this->assertSame('Türkiye Milli Takımı', $player->national_team_name);
        $this->assertSame('TR', $player->national_team_code);
        $this->assertSame('1999-01-01', $player->birth_date->format('Y-m-d'));
        $this->assertSame(10_000_000, $player->market_value_amount);
        $this->assertDatabaseHas('player_follows', ['player_id' => $player->id, 'user_id' => $follower->id]);
        $this->assertDatabaseHas('player_chat_messages', ['id' => $message->id, 'player_id' => $player->id]);
        $this->assertDatabaseCount('players', 1);
    }

    public function test_missing_optional_api_values_do_not_clear_existing_values(): void
    {
        $admin = $this->admin();
        [$team] = $this->linkedTeam();
        $player = Player::factory()->create([
            'provider_player_id' => 'player-1',
            'position' => 'Forvet',
            'shirt_number' => 7,
            'nationality' => 'Türkiye',
        ]);
        Http::fake(['*' => Http::response($this->squadResponse([
            $this->apiPlayer(['position' => null, 'number' => null, 'country' => null]),
        ]))]);

        $token = $this->previewToken($admin, $team);
        $this->apply($admin, $team, $token, [$this->choice('player-1', 'sync')]);
        $player->refresh();

        $this->assertSame('Forvet', $player->position);
        $this->assertSame(7, $player->shirt_number);
        $this->assertSame('Türkiye', $player->nationality);
    }

    public function test_manual_mapping_binds_existing_player_without_automatic_matching_or_duplicate(): void
    {
        $admin = $this->admin();
        [$team] = $this->linkedTeam();
        $manual = Player::factory()->create([
            'name' => '  API   OYUNCUSU ',
            'slug' => 'manuel-kayit',
            'current_team_id' => $team->id,
            'provider_player_id' => null,
            'photo_path' => 'players/photos/manual.jpg',
            'cover_image_path' => 'players/covers/manual.jpg',
            'bio' => 'Korunur',
            'status' => PlayerStatus::Inactive,
        ]);
        Http::fake(['*' => Http::response($this->squadResponse())]);

        $preview = app(PlayerSquadSyncService::class)->createPreview($admin, $team);
        $this->assertCount(1, $preview['groups']['needs_manual_mapping']);
        $this->assertNull($manual->fresh()->provider_player_id);

        $this->apply($admin, $team, $preview['token'], [
            $this->choice('player-1', 'map', $manual->id),
        ]);

        $manual->refresh();
        $this->assertSame('player-1', $manual->provider_player_id);
        $this->assertSame('API Oyuncusu', $manual->name);
        $this->assertSame('manuel-kayit', $manual->slug);
        $this->assertSame('players/photos/manual.jpg', $manual->photo_path);
        $this->assertSame('players/covers/manual.jpg', $manual->cover_image_path);
        $this->assertSame('Korunur', $manual->bio);
        $this->assertSame(PlayerStatus::Inactive, $manual->status);
        $this->assertDatabaseCount('players', 1);
    }

    public function test_manual_mapping_rejects_player_from_another_team(): void
    {
        $admin = $this->admin();
        [$team] = $this->linkedTeam();
        $otherTeam = $this->team(['name' => 'Başka Takım']);
        $otherPlayer = Player::factory()->create([
            'current_team_id' => $otherTeam->id,
            'provider_player_id' => null,
        ]);
        Http::fake(['*' => Http::response($this->squadResponse())]);
        $token = $this->previewToken($admin, $team);

        $this->apply($admin, $team, $token, [
            $this->choice('player-1', 'map', $otherPlayer->id),
        ])->assertSessionHasErrors('import');

        $this->assertNull($otherPlayer->fresh()->provider_player_id);
        $this->assertDatabaseCount('players', 1);
    }

    public function test_local_not_in_squad_contains_only_players_with_provider_ids(): void
    {
        $admin = $this->admin();
        [$team] = $this->linkedTeam();
        $manual = Player::factory()->create(['current_team_id' => $team->id, 'provider_player_id' => null]);
        $mappedMissing = Player::factory()->create(['current_team_id' => $team->id, 'provider_player_id' => 'missing-player']);
        Http::fake(['*' => Http::response($this->squadResponse())]);

        $preview = app(PlayerSquadSyncService::class)->createPreview($admin, $team);
        $missingIds = $preview['local_not_in_provider_squad']->pluck('id');

        $this->assertTrue($missingIds->contains($mappedMissing->id));
        $this->assertFalse($missingIds->contains($manual->id));
    }

    public function test_unselected_player_is_not_imported(): void
    {
        $admin = $this->admin();
        [$team] = $this->linkedTeam();
        Http::fake(['*' => Http::response($this->squadResponse([
            $this->apiPlayer(),
            $this->apiPlayer(['id' => 'player-2', 'name' => 'Seçilmedi']),
        ]))]);
        $token = $this->previewToken($admin, $team);

        $this->apply($admin, $team, $token, [
            $this->choice('player-1', 'create'),
            $this->choice('player-2', 'create', selected: false),
        ]);

        $this->assertDatabaseHas('players', ['provider_player_id' => 'player-1']);
        $this->assertDatabaseMissing('players', ['provider_player_id' => 'player-2']);
    }

    public function test_api_failure_leaves_database_unchanged_and_does_not_expose_key(): void
    {
        $admin = $this->admin();
        [$team] = $this->linkedTeam();
        Http::fake(['*' => Http::response(['success' => false], 401)]);

        $response = $this->actingAs($admin)->post(route('admin.players.import.preview'), ['team_id' => $team->id]);

        $response->assertSessionHasErrors('team_id')->assertDontSee('fixture-only-secret');
        $this->assertDatabaseCount('players', 0);
    }

    public function test_preview_token_is_scoped_expires_and_is_single_use(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();
        [$team] = $this->linkedTeam();
        [$otherTeam] = $this->linkedTeam('team-2', 'Diğer Takım');
        Http::fake(['*' => Http::response($this->squadResponse())]);
        $token = $this->previewToken($admin, $team);

        $this->apply($otherAdmin, $team, $token, [$this->choice('player-1', 'create')])
            ->assertSessionHasErrors('import');
        $this->apply($admin, $otherTeam, $token, [$this->choice('player-1', 'create')])
            ->assertSessionHasErrors('import');
        $this->assertDatabaseCount('players', 0);

        $this->apply($admin, $team, $token, [$this->choice('player-1', 'create')])
            ->assertRedirect(route('admin.players.index'));
        $this->apply($admin, $team, $token, [$this->choice('player-1', 'sync')])
            ->assertSessionHasErrors('import');
        $this->assertDatabaseCount('players', 1);

        $expiredToken = $this->previewToken($admin, $team);
        $this->travel(11)->minutes();
        $this->apply($admin, $team, $expiredToken, [$this->choice('player-1', 'sync')])
            ->assertSessionHasErrors('import');
        $this->travelBack();
    }

    public function test_cached_preview_contains_normalized_data_but_not_api_key_or_raw_fields(): void
    {
        $admin = $this->admin();
        [$team] = $this->linkedTeam();
        Http::fake(['*' => Http::response($this->squadResponse())]);
        $preview = app(PlayerSquadSyncService::class)->createPreview($admin, $team);
        $cacheKey = 'football:player-squad-preview:'.hash('sha256', "{$admin->id}:{$team->id}:{$preview['token']}");
        $cached = Cache::get($cacheKey);
        $serialized = json_encode($cached);

        $this->assertStringNotContainsString('fixture-only-secret', $serialized);
        $this->assertStringNotContainsString('https://cdn.test/player.png', $serialized);
        $this->assertStringNotContainsString('yellow_cards', $serialized);
        $this->assertSame('Türkiye', $cached['players'][0]['nationality']);
    }

    public function test_transaction_failure_does_not_leave_partial_import(): void
    {
        $admin = $this->admin();
        [$team] = $this->linkedTeam();
        Http::fake(['*' => Http::response($this->squadResponse([
            $this->apiPlayer(),
            $this->apiPlayer(['id' => 'player-2', 'name' => 'İkinci Oyuncu']),
        ]))]);
        $token = $this->previewToken($admin, $team);
        Player::creating(function (Player $player): void {
            if ($player->provider_player_id === 'player-2') {
                throw new RuntimeException('Forced transaction failure.');
            }
        });

        $this->apply($admin, $team, $token, [
            $this->choice('player-1', 'create'),
            $this->choice('player-2', 'create'),
        ])->assertSessionHasErrors('import');

        $this->assertDatabaseCount('players', 0);
    }

    public function test_soft_deleted_mapped_player_is_updated_without_restore(): void
    {
        $admin = $this->admin();
        [$team] = $this->linkedTeam();
        $player = Player::factory()->create(['provider_player_id' => 'player-1', 'name' => 'Eski Ad']);
        $player->delete();
        Http::fake(['*' => Http::response($this->squadResponse())]);
        $token = $this->previewToken($admin, $team);

        $this->apply($admin, $team, $token, [$this->choice('player-1', 'sync')]);

        $player = Player::withTrashed()->findOrFail($player->id);
        $this->assertSame('API Oyuncusu', $player->name);
        $this->assertSame($team->id, $player->current_team_id);
        $this->assertSoftDeleted($player);
    }

    public function test_provider_player_id_unique_constraint_is_enforced(): void
    {
        Player::factory()->create(['provider_player_id' => 'unique-provider-player']);

        $this->expectException(QueryException::class);
        Player::factory()->create(['provider_player_id' => 'unique-provider-player']);
    }

    private function previewToken(User $admin, Team $team): string
    {
        $response = $this->actingAs($admin)->post(route('admin.players.import.preview'), ['team_id' => $team->id]);
        $response->assertRedirect()->assertSessionHasNoErrors();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query['token'];
    }

    private function apply(User $admin, Team $team, string $token, array $players): TestResponse
    {
        return $this->actingAs($admin)->post(route('admin.players.import.apply'), [
            'team_id' => $team->id,
            'token' => $token,
            'players' => $players,
        ]);
    }

    private function choice(string $providerPlayerId, string $action, ?int $localPlayerId = null, bool $selected = true): array
    {
        return [
            'provider_player_id' => $providerPlayerId,
            'selected' => $selected ? '1' : '0',
            'action' => $action,
            'local_player_id' => $localPlayerId,
        ];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function linkedTeam(string $providerTeamId = 'team-1', string $name = 'Bağlı Takım'): array
    {
        $team = $this->team(['name' => $name, 'slug' => $providerTeamId]);
        $footballTeam = FootballTeam::create([
            'provider' => LiveFootballApiService::PROVIDER,
            'provider_team_id' => $providerTeamId,
            'provider_name' => $name,
            'is_active' => true,
            'team_id' => $team->id,
        ]);

        return [$team, $footballTeam];
    }

    private function team(array $attributes = []): Team
    {
        return Team::create(array_merge([
            'name' => 'Takım',
            'slug' => 'takim-'.str()->random(8),
            'short_name' => 'TKM',
            'primary_color' => '#111111',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
        ], $attributes));
    }

    private function squadResponse(?array $squad = null, string $teamId = 'team-1'): array
    {
        return [
            'success' => true,
            'message' => 'Success',
            'data' => [
                'team_id' => $teamId,
                'season' => '2026/2027',
                'squad' => $squad ?? [$this->apiPlayer()],
            ],
        ];
    }

    private function apiPlayer(array $overrides = []): array
    {
        return array_merge([
            'id' => 'player-1',
            'name' => 'API Oyuncusu',
            'image' => 'https://cdn.test/player.png',
            'number' => '9',
            'position' => 'Midfielder',
            'age' => '27',
            'country' => 'Türkiye',
            'stats' => ['matches' => '10', 'goals' => '3'],
            'yellow_cards' => 1,
            'red_cards' => 0,
        ], $overrides);
    }
}
