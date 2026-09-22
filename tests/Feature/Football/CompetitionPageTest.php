<?php

namespace Tests\Feature\Football;

use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompetitionPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'https://tribunasyon.test');
        config()->set('app.name', 'Tribünasyon');
        config()->set('services.live_football_api', [
            'key' => 'test-key',
            'base_url' => 'https://football.test/api/v1',
            'nations_league_turkey_team_id' => 'turkey-provider-id',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00', 'Europe/Istanbul'));
        Http::fake(['*' => Http::response($this->standingsResponse())]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_active_competition_page_renders_overview_fixture_states_links_standings_teams_and_seo(): void
    {
        $competition = $this->competition();
        $turkey = $this->team('turkey-provider-id', 'Türkiye');
        $spain = $this->team('spain-id', 'İspanya');
        $france = $this->team('france-id', 'Fransa');
        $finished = $this->match($competition, $turkey, $spain, 'past', '2026-09-20 17:45:00', [
            'round' => 'League A', 'status' => 'finished', 'home_score' => 2, 'away_score' => 1,
            'meta' => ['group' => 'Group 2'],
        ]);
        $live = $this->match($competition, $spain, $france, 'live', '2026-09-22 08:00:00', [
            'round' => 'League A', 'status' => 'live', 'status_display' => "67'", 'is_live' => true, 'home_score' => 1, 'away_score' => 0, 'live_minute' => 67,
            'meta' => ['group' => 'Group 1'],
        ]);
        $halfTime = $this->match($competition, $turkey, $spain, 'half-time', '2026-09-22 09:00:00', [
            'status' => 'halftime', 'status_display' => 'HT', 'is_live' => true, 'home_score' => 0, 'away_score' => 0,
        ]);
        $scheduled = $this->match($competition, $turkey, $france, 'future', '2026-09-24 17:45:00', [
            'round' => 'League A', 'meta' => ['group' => 'Group 2'],
        ]);
        $postponed = $this->match($competition, $spain, $turkey, 'postponed', '2026-09-27 17:45:00', [
            'status' => 'postponed',
        ]);

        $otherCompetition = $this->competition(['provider_league_id' => 'other-league', 'slug' => 'other']);
        $other = $this->team('other-team', 'Başka Takım');
        $this->match($otherCompetition, $other, $france, 'other-match', '2026-09-22 17:00:00');

        $overview = $this->get(route('competitions.show', $competition->slug))
            ->assertOk()
            ->assertSee('UEFA Uluslar Ligi')
            ->assertSee('2026/2027')
            ->assertSee('2026/27')
            ->assertSee('Maç Programı')
            ->assertSee('BUGÜN • 22 EYLÜL SALI')
            ->assertSee('24 EYLÜL PERŞEMBE')
            ->assertSee('League A • Group 1')
            ->assertSee('League A • Group 2')
            ->assertSee('class="competition-match-row is-turkey"', false)
            ->assertSee(route('matches.show', $live), false)
            ->assertSee(route('matches.show', $halfTime), false)
            ->assertSee(route('matches.show', $scheduled), false)
            ->assertSee('1 - 0')
            ->assertSee('67′')
            ->assertSee('CANLI')
            ->assertSee('DEVRE')
            ->assertSee('0 - 0')
            ->assertSee('20:45')
            ->assertSee('Başlamadı')
            ->assertSee('Tüm fikstürü görüntüle')
            ->assertDontSee(route('matches.show', $finished), false)
            ->assertDontSee('Türkiye’nin Maçları')
            ->assertDontSee('Bugünün Maçları')
            ->assertDontSee('Son Sonuçlar')
            ->assertDontSee('Başka Takım')
            ->assertSee('UEFA Uluslar Ligi 2026/2027 Fikstür, Puan Durumu ve Maçlar | Tribünasyon')
            ->assertSee('<link rel="canonical" href="https://tribunasyon.test/turnuva/uluslar-ligi">', false);

        $this->assertSame(1, substr_count($overview->getContent(), route('matches.show', $live)));

        $this->get(route('competitions.show', ['competition' => $competition->slug, 'tab' => 'fikstur']))
            ->assertOk()
            ->assertSee('Tümü')
            ->assertSee('Yaklaşan')
            ->assertSee('Tamamlanan')
            ->assertSee('20 EYLÜL PAZAR')
            ->assertSee('BUGÜN • 22 EYLÜL SALI')
            ->assertSee('24 EYLÜL PERŞEMBE')
            ->assertSee('20:45')
            ->assertSee('2 - 1')
            ->assertSee('Bitti')
            ->assertSee(route('matches.show', $finished), false)
            ->assertSee(route('matches.show', $live), false)
            ->assertSee(route('matches.show', $scheduled), false)
            ->assertSee(route('matches.show', $postponed), false)
            ->assertSee('Ertelendi')
            ->assertDontSee('Başka Takım');

        $this->get(route('competitions.show', ['competition' => $competition->slug, 'tab' => 'fikstur', 'filter' => 'yaklasan']))
            ->assertOk()
            ->assertSee(route('matches.show', $live), false)
            ->assertSee(route('matches.show', $scheduled), false)
            ->assertDontSee(route('matches.show', $finished), false)
            ->assertDontSee(route('matches.show', $postponed), false);

        $this->get(route('competitions.show', ['competition' => $competition->slug, 'tab' => 'fikstur', 'filter' => 'tamamlanan']))
            ->assertOk()
            ->assertSee(route('matches.show', $finished), false)
            ->assertDontSee(route('matches.show', $live), false)
            ->assertDontSee(route('matches.show', $scheduled), false)
            ->assertDontSee(route('matches.show', $postponed), false);

        Http::assertNothingSent();

        $this->get(route('competitions.show', ['competition' => $competition->slug, 'tab' => 'puan-durumu']))
            ->assertOk()
            ->assertSee('League A')
            ->assertSee('Group 1')
            ->assertSee('data-turkey-team="true"', false);

        $teams = $this->get(route('competitions.show', ['competition' => $competition->slug, 'tab' => 'takimlar']))
            ->assertOk()
            ->assertSee('3 takım')
            ->assertSee('Türkiye')
            ->assertSee('İspanya')
            ->assertSee('Fransa');

        $this->assertSame(3, substr_count($teams->getContent(), 'class="competition-team-card"'));
    }

    public function test_inactive_competition_is_not_public_and_turkey_is_never_matched_by_name(): void
    {
        $inactive = $this->competition(['provider_league_id' => 'inactive-league', 'slug' => 'inactive', 'is_active' => false]);
        $this->get(route('competitions.show', $inactive->slug))->assertNotFound();

        config()->set('services.live_football_api.nations_league_turkey_team_id', 'missing-provider-id');
        $competition = $this->competition();
        $namedTurkey = $this->team('different-provider-id', 'Türkiye');
        $opponent = $this->team('opponent-id', 'Rakip');
        $this->match($competition, $namedTurkey, $opponent, 'named-turkey', '2026-09-24 17:45:00');

        $this->get(route('competitions.show', $competition->slug))
            ->assertOk()
            ->assertSee('Bugün maç bulunmuyor.')
            ->assertSee('Yaklaşan maçlar')
            ->assertDontSee('Türkiye’nin Maçları')
            ->assertDontSee('data-turkey-team="true"', false);
    }

    public function test_batch_state_is_scoped_to_the_active_competition(): void
    {
        $competition = $this->competition();
        $home = $this->team('home', 'Ev');
        $away = $this->team('away', 'Deplasman');
        $live = $this->match($competition, $home, $away, 'live-state', '2026-09-22 08:00:00', [
            'status' => 'live', 'is_live' => true, 'home_score' => 3, 'away_score' => 2, 'live_minute' => 71,
        ]);
        $otherCompetition = $this->competition(['provider_league_id' => 'other-state-league', 'slug' => 'other-state']);
        $other = $this->match($otherCompetition, $home, $away, 'other-state', '2026-09-22 08:00:00');

        $this->getJson(route('competitions.state', ['competition' => $competition->slug, 'ids' => [$live->id]]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath("matches.{$live->id}.score.home", 3)
            ->assertJsonPath("matches.{$live->id}.minute", 71);

        $this->getJson(route('competitions.state', ['competition' => $competition->slug, 'ids' => [$other->id]]))
            ->assertUnprocessable();
    }

    public function test_matches_page_links_to_nations_league_center_and_standings_cache_is_reused(): void
    {
        $competition = $this->competition();
        $home = $this->team('home', 'Ev');
        $away = $this->team('away', 'Deplasman');
        $this->match($competition, $home, $away, 'today', '2026-09-22 17:45:00');

        $this->get(route('matches.index', ['competition' => FootballCompetition::NATIONS_LEAGUE_PROVIDER_ID]))
            ->assertOk()
            ->assertSee('Uluslar Ligi Merkezi')
            ->assertSee(route('competitions.show', 'uluslar-ligi'), false);

        $this->get(route('competitions.show', $competition->slug))->assertOk();
        $this->get(route('competitions.show', ['competition' => $competition->slug, 'tab' => 'puan-durumu']))->assertOk();

        Http::assertSentCount(1);
        $this->assertNotNull(Cache::get('football:standings:'.FootballCompetition::NATIONS_LEAGUE_PROVIDER_ID.':2026/2027'));
    }

    public function test_competition_tabs_eager_load_match_and_team_relations(): void
    {
        $competition = $this->competition();
        $home = $this->team('home-eager', 'Ev');
        $away = $this->team('away-eager', 'Deplasman');
        $this->match($competition, $home, $away, 'eager-match', '2026-09-22 17:45:00');

        Model::preventLazyLoading();

        try {
            foreach ([null, 'fikstur', 'puan-durumu', 'takimlar'] as $tab) {
                $this->get(route('competitions.show', [
                    'competition' => $competition->slug,
                    'tab' => $tab,
                ]))->assertOk();
            }
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    private function competition(array $attributes = []): FootballCompetition
    {
        return FootballCompetition::create(array_merge([
            'provider' => 'live-football-api',
            'provider_league_id' => FootballCompetition::NATIONS_LEAGUE_PROVIDER_ID,
            'name' => 'UEFA Nations League',
            'display_name' => 'Uluslar Ligi',
            'slug' => 'uluslar-ligi',
            'current_season' => '2026/2027',
            'timezone' => 'UTC',
            'is_active' => true,
            'sort_order' => 15,
        ], $attributes));
    }

    private function team(string $providerId, string $name): FootballTeam
    {
        return FootballTeam::create([
            'provider' => 'live-football-api',
            'provider_team_id' => $providerId,
            'provider_name' => $name,
            'display_name' => $name,
            'provider_logo_url' => "https://cdn.test/{$providerId}.png",
            'country' => $name,
            'is_active' => true,
        ]);
    }

    private function match(FootballCompetition $competition, FootballTeam $home, FootballTeam $away, string $providerId, string $kickoff, array $attributes = []): FootballMatch
    {
        return FootballMatch::create(array_merge([
            'competition_id' => $competition->id,
            'home_football_team_id' => $home->id,
            'away_football_team_id' => $away->id,
            'provider' => 'live-football-api',
            'provider_match_id' => $providerId,
            'season' => '2026/2027',
            'kickoff_at' => $kickoff,
            'status' => 'scheduled',
            'is_live' => false,
        ], $attributes));
    }

    private function standingsResponse(): array
    {
        $row = fn (int $rank, string $id, string $name): array => [
            'rank' => (string) $rank,
            'team' => ['id' => $id, 'name' => $name, 'logo' => "https://cdn.test/{$id}.png"],
            'played' => '2', 'won' => '1', 'drawn' => '1', 'lost' => '0',
            'goals_for' => '3', 'goals_against' => '1', 'goal_diff' => '2', 'points' => '4',
            'zone' => ['name' => 'Promotion', 'color' => '#00d9c4'],
        ];

        return ['success' => true, 'data' => [
            'league_id' => FootballCompetition::NATIONS_LEAGUE_PROVIDER_ID,
            'season' => '2026/2027',
            'available_seasons' => ['2026/2027'],
            'standings' => [[
                'title' => 'League A - Group 1',
                'table' => [$row(1, 'turkey-provider-id', 'Türkiye'), $row(2, 'spain-id', 'İspanya')],
            ]],
        ]];
    }
}
