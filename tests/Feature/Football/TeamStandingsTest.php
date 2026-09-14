<?php

namespace Tests\Feature\Football;

use App\Enums\TeamStatus;
use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use App\Models\Team;
use App\Services\Football\LeagueStandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamStandingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('app.name', 'Tribünasyon');
        config()->set('app.url', 'https://tribunasyon.test');
        config()->set('services.live_football_api', [
            'key' => 'standings-secret-key',
            'base_url' => 'https://football.test/api/v1',
        ]);
    }

    public function test_linked_team_can_open_standings_with_profile_tabs_form_and_seo(): void
    {
        $team = $this->team(['name' => 'Fenerbahçe', 'slug' => 'fenerbahce']);
        $competition = $this->competition();
        $linked = $this->footballTeam('provider-fener', 'Sağlayıcı Fener', $team);
        $this->linkToCompetition($competition, $linked);
        Http::fake(['football.test/api/v1/league_standings*' => Http::response($this->response([
            $this->row('provider-fener', 'Fenerbahçe', ['form' => 'WDLWL']),
        ]))]);

        $response = $this->get(route('teams.standings', $team))
            ->assertOk()
            ->assertSee('Puan Durumu')
            ->assertSee('2026/2027')
            ->assertSee('Fenerbahçe Puan Durumu | Tribünasyon')
            ->assertSee('Fenerbahçe’nin ligdeki güncel sıralaması, puanı, averajı ve son 5 maç formu.')
            ->assertSee('<link rel="canonical" href="https://tribunasyon.test/takim/fenerbahce/puan-durumu">', false)
            ->assertSee(route('teams.show', $team), false)
            ->assertSee(route('teams.fixtures', $team), false)
            ->assertSee(route('teams.players', $team), false)
            ->assertSee('form-win', false)
            ->assertSee('form-draw', false)
            ->assertSee('form-loss', false)
            ->assertDontSee('standings-secret-key');

        $this->assertSame(5, substr_count($response->getContent(), 'class="form-result'));
    }

    public function test_unlinked_team_and_team_without_competition_mapping_do_not_fail(): void
    {
        Http::fake();
        $unlinked = $this->team();

        $this->get(route('teams.standings', $unlinked))
            ->assertOk()
            ->assertSee('Bu takım için puan durumu henüz mevcut değil.');

        $linkedWithoutMatches = $this->team(['name' => 'Bağlı', 'slug' => 'bagli']);
        $this->footballTeam('linked-without-league', 'Bağlı', $linkedWithoutMatches);

        $this->get(route('teams.standings', $linkedWithoutMatches))
            ->assertOk()
            ->assertSee('Bu takım için puan durumu henüz mevcut değil.');

        Http::assertNothingSent();
    }

    public function test_standings_are_normalized_from_real_provider_scalar_shapes(): void
    {
        $normalized = app(LeagueStandingsService::class)->normalize($this->responseData([
            $this->row('team-1', 'Takım 1', [
                'rank' => '2',
                'played' => '5',
                'won' => '3',
                'drawn' => '1',
                'lost' => '1',
                'goals_for' => '10',
                'goals_against' => '6',
                'goal_diff' => '-4',
                'points' => '10',
                'form' => 'wdlww',
                'zone' => ['name' => 'Europa League', 'color' => '#12abEF'],
            ]),
        ]));

        $this->assertSame('2026/2027', $normalized['season']);
        $this->assertSame([
            'rank' => 2,
            'provider_team_id' => 'team-1',
            'team_name' => 'Takım 1',
            'team_logo' => 'https://cdn.test/team-1.png',
            'played' => 5,
            'won' => 3,
            'drawn' => 1,
            'lost' => 1,
            'goals_for' => 10,
            'goals_against' => 6,
            'goal_diff' => -4,
            'points' => 10,
            'form' => 'WDLWW',
            'zone_name' => 'Europa League',
            'zone_color' => '#12abEF',
        ], $normalized['tables'][0]['rows'][0]);
    }

    public function test_current_team_is_highlighted_only_by_provider_id(): void
    {
        $team = $this->team(['name' => 'Galatasaray', 'slug' => 'galatasaray']);
        $competition = $this->competition();
        $linked = $this->footballTeam('linked-id', 'Unrelated provider label', $team);
        $this->linkToCompetition($competition, $linked);
        Http::fake(['*' => Http::response($this->response([
            $this->row('same-name-only', 'Galatasaray'),
            $this->row('linked-id', 'Completely Different Name', ['rank' => 2]),
        ]))]);

        $html = $this->get(route('teams.standings', $team))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-current-team="true"'));
        $this->assertMatchesRegularExpression('/data-current-team="true"[^>]*>.*Completely Different Name/s', $html);
        $this->assertDoesNotMatchRegularExpression('/data-current-team="true"[^>]*>.*?Galatasaray.*?<\/tr>/s', $html);
    }

    public function test_same_league_uses_one_cached_payload_across_team_pages(): void
    {
        $competition = $this->competition();
        $firstTeam = $this->team(['name' => 'Birinci', 'slug' => 'birinci']);
        $secondTeam = $this->team(['name' => 'İkinci', 'slug' => 'ikinci']);
        $first = $this->footballTeam('first-id', 'Birinci', $firstTeam);
        $second = $this->footballTeam('second-id', 'İkinci', $secondTeam);
        $this->linkToCompetition($competition, $first, $second);
        Http::fake(['*' => Http::response($this->response([
            $this->row('first-id', 'Birinci'),
            $this->row('second-id', 'İkinci', ['rank' => 2]),
        ]))]);

        $this->get(route('teams.standings', $firstTeam))->assertOk();
        $this->get(route('teams.standings', $secondTeam))->assertOk();

        Http::assertSentCount(1);
        $this->assertNotNull(Cache::get('football:standings:test-league:current'));
    }

    public function test_lowest_existing_competition_sort_order_is_the_default_without_name_matching(): void
    {
        $team = $this->team();
        $linked = $this->footballTeam('linked-id', 'Bağlı', $team);
        $preferred = $this->competition(['provider_league_id' => 'preferred-id', 'name' => 'Arbitrary A', 'slug' => 'arbitrary-a', 'country' => null, 'sort_order' => 10]);
        $other = $this->competition(['provider_league_id' => 'other-id', 'name' => 'Arbitrary B', 'slug' => 'arbitrary-b', 'country' => 'Türkiye', 'sort_order' => 20]);
        $this->linkToCompetition($preferred, $linked);
        $this->linkToCompetition($other, $linked);
        Http::fake(['*' => Http::response($this->response([$this->row('linked-id', 'Bağlı')], 'preferred-id'))]);

        $this->get(route('teams.standings', $team))->assertOk();

        Http::assertSent(fn (Request $request): bool => $request['league_id'] === 'preferred-id');
    }

    public function test_malformed_payload_and_provider_errors_render_safe_fallbacks(): void
    {
        $team = $this->team();
        $competition = $this->competition();
        $linked = $this->footballTeam('linked-id', 'Bağlı', $team);
        $this->linkToCompetition($competition, $linked);

        Http::fake(['*' => Http::response(['success' => true, 'data' => ['league_id' => 'test-league', 'standings' => 'bad']])]);
        $this->get(route('teams.standings', $team))->assertOk()->assertSee('Puan durumu şu anda görüntülenemiyor.');

        foreach ([429, 503] as $status) {
            Cache::flush();
            Http::fake(['*' => Http::response(['success' => false], $status)]);
            $this->get(route('teams.standings', $team))->assertOk()->assertSee('Puan durumu şu anda görüntülenemiyor.');
        }
    }

    public function test_malformed_rows_and_unsafe_provider_presentation_values_are_ignored(): void
    {
        $team = $this->team();
        $competition = $this->competition();
        $linked = $this->footballTeam('linked-id', 'Bağlı', $team);
        $this->linkToCompetition($competition, $linked);
        $valid = $this->row('linked-id', 'Bağlı', [
            'form' => 'WWW<script>',
            'zone' => ['name' => 'Zone', 'color' => 'red;display:none'],
            'team' => ['id' => 'linked-id', 'name' => 'Bağlı', 'logo' => 'javascript:alert(1)'],
        ]);
        Http::fake(['*' => Http::response($this->response([['rank' => 1], $valid]))]);

        $this->get(route('teams.standings', $team))
            ->assertOk()
            ->assertSee('Bağlı')
            ->assertDontSee('javascript:alert', false)
            ->assertDontSee('red;display', false)
            ->assertDontSee('form-win', false);
    }

    public function test_stale_cached_standings_are_used_when_refresh_fails(): void
    {
        $team = $this->team();
        $competition = $this->competition();
        $linked = $this->footballTeam('linked-id', 'Bağlı', $team);
        $this->linkToCompetition($competition, $linked);
        $stale = app(LeagueStandingsService::class)->normalize($this->responseData([$this->row('linked-id', 'Stale Takım')]));
        Cache::put('football:standings:test-league:current:stale', $stale, now()->addHour());
        Http::fake(['*' => Http::response([], 503)]);

        $this->get(route('teams.standings', $team))->assertOk()->assertSee('Stale Takım');
    }

    private function team(array $attributes = []): Team
    {
        return Team::create(array_merge([
            'name' => 'Deneme Takımı',
            'slug' => 'deneme-takimi',
            'short_name' => 'DNT',
            'primary_color' => '#111111',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
        ], $attributes));
    }

    private function competition(array $attributes = []): FootballCompetition
    {
        return FootballCompetition::create(array_merge([
            'provider' => 'live-football-api',
            'provider_league_id' => 'test-league',
            'name' => 'Test Ligi',
            'display_name' => 'Test Ligi',
            'slug' => 'test-ligi',
            'is_active' => true,
            'sort_order' => 10,
        ], $attributes));
    }

    private function footballTeam(string $providerId, string $name, ?Team $team = null): FootballTeam
    {
        return FootballTeam::create([
            'provider' => 'live-football-api',
            'provider_team_id' => $providerId,
            'provider_name' => $name,
            'display_name' => $name,
            'team_id' => $team?->id,
            'is_active' => true,
        ]);
    }

    private function linkToCompetition(FootballCompetition $competition, FootballTeam $home, ?FootballTeam $away = null): FootballMatch
    {
        static $sequence = 0;
        $sequence++;
        $away ??= $this->footballTeam('opponent-'.$sequence, 'Rakip '.$sequence);

        return FootballMatch::create([
            'competition_id' => $competition->id,
            'home_football_team_id' => $home->id,
            'away_football_team_id' => $away->id,
            'provider' => 'live-football-api',
            'provider_match_id' => 'standing-match-'.$sequence,
            'kickoff_at' => '2026-09-10 18:00:00',
            'status' => 'scheduled',
            'is_live' => false,
        ]);
    }

    private function response(array $rows, string $leagueId = 'test-league'): array
    {
        return ['success' => true, 'data' => $this->responseData($rows, $leagueId)];
    }

    private function responseData(array $rows, string $leagueId = 'test-league'): array
    {
        return [
            'league_id' => $leagueId,
            'season' => '2026/2027',
            'available_seasons' => ['2026/2027'],
            'standings' => [['title' => 'Test Ligi', 'table' => $rows]],
            'home_standings' => [],
            'away_standings' => [],
        ];
    }

    private function row(string $providerId, string $name, array $overrides = []): array
    {
        return array_replace([
            'rank' => '1',
            'team' => ['id' => $providerId, 'name' => $name, 'logo' => "https://cdn.test/{$providerId}.png"],
            'played' => '5',
            'won' => '4',
            'drawn' => '1',
            'lost' => '0',
            'goals_for' => '13',
            'goals_against' => '6',
            'goal_diff' => '7',
            'points' => '13',
            'form' => 'WWDWW',
            'zone' => ['name' => 'Champions League', 'color' => '#02206B'],
        ], $overrides);
    }
}
