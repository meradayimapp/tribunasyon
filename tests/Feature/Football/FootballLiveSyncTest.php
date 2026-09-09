<?php

namespace Tests\Feature\Football;

use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FootballLiveSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.live_football_api', [
            'key' => 'test-secret-key',
            'base_url' => 'https://football.test/api/v1',
        ]);
        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-09-09 17:05:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_live_command_only_refreshes_candidate_date_and_details_for_truly_live_matches(): void
    {
        $match = $this->match();
        $farMatch = $this->match([
            'provider_match_id' => 'far-match',
            'kickoff_at' => '2026-09-12 17:00:00',
            'status_display' => 'Uzak',
        ]);
        Http::fake([
            'football.test/api/v1/matches*' => Http::response($this->matchesResponse()),
            'football.test/api/v1/live_match_details*' => Http::response($this->detailsResponse()),
        ]);

        $this->artisan('football:sync-live')
            ->expectsOutput('1 aday değerlendirildi; 1 canlı detay güncellendi.')
            ->assertExitCode(0);

        $match->refresh();
        $this->assertTrue($match->is_live);
        $this->assertSame(37, $match->live_minute);
        $this->assertSame(1, $match->home_score);
        $this->assertSame('Gol', $match->live_events[0]['label']);
        $this->assertSame('Oyuncu', $match->live_events[0]['player_name']);
        $this->assertSame('Uzak', $farMatch->fresh()->status_display);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/live_match_details')
            && $request['match_id'] === 'live-match');

        $this->artisan('football:sync-live')->assertExitCode(0);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('football_matches', 2);
    }

    public function test_api_failure_preserves_existing_live_match_data(): void
    {
        $match = $this->match([
            'status' => 'live', 'status_display' => "28'", 'is_live' => true,
            'home_score' => 2, 'away_score' => 1, 'live_minute' => 28,
            'live_events' => [['time' => '10', 'type' => 'goal', 'label' => 'Gol']],
        ]);
        Http::fake(['*' => Http::response(['success' => false], 503)]);

        $this->artisan('football:sync-live')->assertExitCode(1);

        $match->refresh();
        $this->assertSame(2, $match->home_score);
        $this->assertSame(1, $match->away_score);
        $this->assertSame(28, $match->live_minute);
        $this->assertSame('Gol', $match->live_events[0]['label']);
    }

    public function test_no_candidate_means_no_upstream_request(): void
    {
        $this->match(['kickoff_at' => '2026-09-20 17:00:00']);
        Http::fake();

        $this->artisan('football:sync-live')
            ->expectsOutput('0 aday değerlendirildi; 0 canlı detay güncellendi.')
            ->assertExitCode(0);
        Http::assertNothingSent();
    }

    private function match(array $overrides = []): FootballMatch
    {
        $competition = FootballCompetition::query()->firstOrCreate(
            ['provider' => 'live-football-api', 'provider_league_id' => 'league-live'],
            ['name' => 'Canlı Lig', 'slug' => 'canli-lig', 'is_active' => true],
        );
        $home = FootballTeam::query()->firstOrCreate(
            ['provider' => 'live-football-api', 'provider_team_id' => 'home-live'],
            ['provider_name' => 'Ev', 'is_active' => true],
        );
        $away = FootballTeam::query()->firstOrCreate(
            ['provider' => 'live-football-api', 'provider_team_id' => 'away-live'],
            ['provider_name' => 'Deplasman', 'is_active' => true],
        );

        return FootballMatch::create(array_merge([
            'competition_id' => $competition->id,
            'home_football_team_id' => $home->id,
            'away_football_team_id' => $away->id,
            'provider' => 'live-football-api',
            'provider_match_id' => 'live-match',
            'kickoff_at' => '2026-09-09 17:00:00',
            'status' => 'scheduled', 'status_display' => 'Başlamadı', 'is_live' => false,
        ], $overrides));
    }

    private function matchesResponse(): array
    {
        return ['success' => true, 'data' => [
            'date' => '2026-09-09', 'matches' => [[
                'id' => 'live-match',
                'league' => ['id' => 'league-live', 'name' => 'Canlı Lig'],
                'date' => '2026-09-09', 'kickoff' => '17:00',
                'status' => ['status' => 'live', 'display' => "35'", 'minute' => '35', 'is_live' => true, 'state' => 'inPlay'],
                'home' => ['id' => 'home-live', 'name' => 'Ev', 'score' => '0'],
                'away' => ['id' => 'away-live', 'name' => 'Deplasman', 'score' => '0'],
            ]],
        ]];
    }

    private function detailsResponse(): array
    {
        return ['success' => true, 'data' => [
            'match_id' => 'live-match',
            'header' => [
                'home' => ['id' => 'home-live', 'name' => 'Ev', 'score' => '1'],
                'away' => ['id' => 'away-live', 'name' => 'Deplasman', 'score' => '0'],
                'status' => ['display' => 'CANLI', 'is_live' => true, 'minute' => '37', 'state' => 'inPlay'],
            ],
            'events' => [
                ['time' => '36', 'type' => 'Goal', 'side' => 'home', 'detail' => ['player' => ['id' => 'p1', 'name' => '<b>Oyuncu</b>'], 'score' => '1-0']],
                ['time' => 'x', 'type' => 'Unknown', 'detail' => ['raw' => '<script>alert(1)</script>']],
            ],
        ]];
    }
}
