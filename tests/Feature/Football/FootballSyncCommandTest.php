<?php

namespace Tests\Feature\Football;

use App\Enums\TeamStatus;
use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\Team;
use Database\Seeders\FootballCompetitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FootballSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.live_football_api', [
            'key' => 'test-secret-key',
            'base_url' => 'https://football.test/api/v1',
        ]);
    }

    public function test_competition_seeder_is_idempotent(): void
    {
        $this->seed(FootballCompetitionSeeder::class);
        $this->seed(FootballCompetitionSeeder::class);

        $this->assertDatabaseCount('football_competitions', 4);
        $this->assertDatabaseHas('football_competitions', [
            'provider_league_id' => '482ofyysbdbeoxauk19yg7tdt',
            'sort_order' => 10,
        ]);
    }

    public function test_fixture_sync_is_idempotent_normalizes_scores_and_links_known_community_teams(): void
    {
        $competition = $this->competition();
        $communityTeam = Team::create([
            'name' => 'Fenerbahçe Topluluğu',
            'slug' => 'fenerbahce',
            'short_name' => 'FB',
            'primary_color' => '#0b2d72',
            'secondary_color' => '#f3d21b',
            'status' => TeamStatus::Active,
        ]);

        Http::fake([
            'football.test/api/v1/league_fixtures*' => Http::response($this->fixtureResponse()),
        ]);

        $this->artisan('football:sync-fixtures')->assertExitCode(0);
        $this->artisan('football:sync-fixtures')->assertExitCode(0);

        $this->assertDatabaseCount('football_matches', 1);
        $this->assertDatabaseCount('football_teams', 2);
        $this->assertDatabaseHas('football_teams', [
            'provider_team_id' => '8lroq0cbhdxj8124qtxwrhvmm',
            'team_id' => $communityTeam->id,
        ]);
        $this->assertDatabaseHas('football_teams', [
            'provider_team_id' => 'arsenal-provider-id',
            'team_id' => null,
        ]);
        $this->assertDatabaseHas('football_matches', [
            'competition_id' => $competition->id,
            'provider_match_id' => 'match-1',
            'home_score' => 2,
            'away_score' => null,
            'home_halftime_score' => 1,
            'away_halftime_score' => 0,
            'tv_broadcast' => 1,
        ]);
        $this->assertSame('2026-09-08 17:00:00', DB::table('football_matches')->value('kickoff_at'));
        $this->assertSame('UTC', FootballMatch::query()->firstOrFail()->kickoff_at->timezoneName);
    }

    public function test_daily_sync_updates_only_active_tracked_competitions(): void
    {
        $this->competition();
        Http::fake([
            'football.test/api/v1/matches*' => Http::response([
                'success' => true,
                'data' => [
                    'date' => '2026-09-08',
                    'timezone' => 'UTC',
                    'matches' => [
                        $this->matchPayload(['home' => ['score' => '3'], 'away' => ['score' => '1']]),
                        $this->matchPayload([
                            'id' => 'ignored-match',
                            'league' => ['id' => 'untracked-league', 'name' => 'Other'],
                        ]),
                    ],
                ],
            ]),
        ]);

        $this->artisan('football:sync-daily', ['date' => '2026-09-08'])
            ->expectsOutput('2026-09-08: 1 maç senkronize edildi.')
            ->expectsOutput('Takip edilmeyen organizasyonlara ait 1 maç atlandı.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('football_matches', 1);
        $this->assertDatabaseHas('football_matches', [
            'provider_match_id' => 'match-1',
            'home_score' => 3,
            'away_score' => 1,
        ]);
    }

    public function test_daily_sync_rejects_an_invalid_calendar_date(): void
    {
        $this->artisan('football:sync-daily', ['date' => '2026-02-31'])
            ->expectsOutput('Geçerli bir tarih girin.')
            ->assertExitCode(2);

        Http::assertNothingSent();
    }

    private function competition(): FootballCompetition
    {
        return FootballCompetition::create([
            'provider' => 'live-football-api',
            'provider_league_id' => '482ofyysbdbeoxauk19yg7tdt',
            'name' => 'Trendyol Süper Lig',
            'display_name' => 'Süper Lig',
            'slug' => 'super-lig',
            'timezone' => 'UTC',
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    private function fixtureResponse(): array
    {
        return [
            'success' => true,
            'data' => [
                'league_id' => '482ofyysbdbeoxauk19yg7tdt',
                'league_name' => 'Trendyol Süper Lig',
                'season' => '2026/2027',
                'timezone' => 'UTC',
                'weeks' => [[
                    'week' => '4',
                    'matches' => [$this->matchPayload()],
                ]],
            ],
        ];
    }

    private function matchPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'match-1',
            'league' => ['id' => '482ofyysbdbeoxauk19yg7tdt', 'name' => 'Trendyol Süper Lig'],
            'round' => 'Normal Sezon',
            'date' => '2026-09-08',
            'kickoff' => '17:00',
            'status' => ['status' => 'scheduled', 'display' => 'Başlamadı', 'is_live' => false, 'state' => 'preGame'],
            'home' => [
                'id' => '8lroq0cbhdxj8124qtxwrhvmm',
                'name' => 'Fenerbahçe',
                'logo' => 'https://cdn.test/fenerbahce.png',
                'score' => '2',
            ],
            'away' => [
                'id' => 'arsenal-provider-id',
                'name' => 'Arsenal',
                'logo' => 'https://cdn.test/arsenal.png',
                'score' => null,
            ],
            'halftime' => ['home' => '1', 'away' => 0],
            'penalty' => ['home' => null, 'away' => null],
            'tv_broadcast' => true,
        ], $overrides);
    }
}
