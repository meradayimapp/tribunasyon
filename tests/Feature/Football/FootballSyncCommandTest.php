<?php

namespace Tests\Feature\Football;

use App\Enums\TeamStatus;
use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\Team;
use Database\Seeders\FootballCompetitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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

        $this->assertDatabaseCount('football_competitions', 11);
        $this->assertDatabaseHas('football_competitions', [
            'provider_league_id' => '482ofyysbdbeoxauk19yg7tdt',
            'sort_order' => 10,
        ]);
        $this->assertDatabaseHas('football_competitions', [
            'provider_league_id' => FootballCompetition::NATIONS_LEAGUE_PROVIDER_ID,
            'display_name' => 'Uluslar Ligi',
            'slug' => 'uluslar-ligi',
            'sort_order' => 15,
        ]);

        $expected = [
            FootballCompetition::PREMIER_LEAGUE_PROVIDER_ID => ['Premier Lig', 'Premier League', 'premier-league', 'İngiltere', 50],
            FootballCompetition::LA_LIGA_PROVIDER_ID => ['LaLiga', 'La Liga', 'la-liga', 'İspanya', 60],
            FootballCompetition::SERIE_A_PROVIDER_ID => ['Serie A', 'Serie A', 'serie-a', 'İtalya', 70],
            FootballCompetition::BUNDESLIGA_PROVIDER_ID => ['Bundesliga', 'Bundesliga', 'bundesliga', 'Almanya', 80],
            FootballCompetition::LIGUE_1_PROVIDER_ID => ['Ligue 1', 'Ligue 1', 'ligue-1', 'Fransa', 90],
            FootballCompetition::PRIMEIRA_LIGA_PROVIDER_ID => ['Primeira Liga', 'Primeira Liga', 'primeira-liga', 'Portekiz', 100],
        ];

        foreach ($expected as $providerId => [$name, $displayName, $slug, $country, $sortOrder]) {
            $this->assertDatabaseHas('football_competitions', [
                'provider' => 'live-football-api',
                'provider_league_id' => $providerId,
                'name' => $name,
                'display_name' => $displayName,
                'slug' => $slug,
                'country' => $country,
                'current_season' => '2026/2027',
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]);
        }
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
        $this->assertDatabaseHas('football_competitions', [
            'id' => $competition->id,
            'country' => 'Türkiye',
            'provider_logo_url' => 'https://cdn.test/super-lig.png',
            'current_season' => '2026/2027',
        ]);
        $this->assertSame('2026-09-08 17:00:00', DB::table('football_matches')->value('kickoff_at'));
        $this->assertSame('UTC', FootballMatch::query()->firstOrFail()->kickoff_at->timezoneName);
    }

    public function test_fixture_sync_targets_every_seeded_competition_once_by_provider_id(): void
    {
        $this->seed(FootballCompetitionSeeder::class);

        Http::fake(fn (Request $request) => Http::response([
            'success' => true,
            'data' => [
                'league_id' => $request['league_id'],
                'league_name' => 'Provider League',
                'season' => '2026/2027',
                'timezone' => 'UTC',
                'weeks' => [],
            ],
        ]));

        $this->artisan('football:sync-fixtures')->assertSuccessful();

        $expectedIds = FootballCompetition::query()->pluck('provider_league_id')->sort()->values()->all();
        $requestedIds = collect(Http::recorded())
            ->map(fn (array $record): string => (string) $record[0]['league_id'])
            ->sort()
            ->values()
            ->all();

        $this->assertCount(11, $requestedIds);
        $this->assertSame($expectedIds, $requestedIds);
    }

    public function test_fixture_sync_can_target_one_competition_by_exact_slug_without_extra_api_calls(): void
    {
        $this->seed(FootballCompetitionSeeder::class);

        Http::fake(fn (Request $request) => Http::response([
            'success' => true,
            'data' => [
                'league_id' => $request['league_id'],
                'league_name' => 'Bundesliga',
                'season' => '2026/2027',
                'timezone' => 'UTC',
                'weeks' => [],
            ],
        ]));

        $this->artisan('football:sync-fixtures', ['--competition' => ['bundesliga']])->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request['league_id'] === FootballCompetition::BUNDESLIGA_PROVIDER_ID);

        $this->artisan('football:sync-fixtures', ['--competition' => ['bundes']])
            ->expectsOutput('İstenen organizasyonlardan en az biri aktif ve kayıtlı değil. Tam slug veya provider league ID kullanın.')
            ->assertExitCode(2);
        Http::assertSentCount(1);
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
                'country' => 'Türkiye',
                'logo' => 'https://cdn.test/super-lig.png',
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
