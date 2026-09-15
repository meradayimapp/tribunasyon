<?php

namespace Tests\Feature\Football;

use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TodayScoresTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, FootballCompetition> */
    private array $competitions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-09 12:00:00', 'UTC'));

        $this->competitions = [
            'super' => $this->competition(FootballCompetition::SUPER_LEAGUE_PROVIDER_ID, 'Trendyol Süper Lig', 'super-lig', 10),
            'champions' => $this->competition(FootballCompetition::CHAMPIONS_LEAGUE_PROVIDER_ID, 'Şampiyonlar Ligi', 'sampiyonlar-ligi', 20),
            'europa' => $this->competition(FootballCompetition::EUROPA_LEAGUE_PROVIDER_ID, 'Avrupa Ligi', 'avrupa-ligi', 30),
            'conference' => $this->competition(FootballCompetition::CONFERENCE_LEAGUE_PROVIDER_ID, 'Konferans Ligi', 'konferans-ligi', 40),
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_global_ribbon_renders_supported_today_matches_in_state_order_with_links(): void
    {
        $finished = $this->match($this->competitions['super'], 'Biten Ev', 'Biten Dep', [
            'kickoff_at' => '2026-09-09 09:00:00',
            'status' => 'finished',
            'status_display' => 'Bitti',
            'home_score' => 2,
            'away_score' => 1,
        ]);
        $upcoming = $this->match($this->competitions['super'], 'Gelecek Ev', 'Gelecek Dep', [
            'kickoff_at' => '2026-09-09 17:00:00',
        ]);
        $live = $this->match($this->competitions['super'], 'Canlı Ev', 'Canlı Dep', [
            'kickoff_at' => '2026-09-09 10:00:00',
            'status' => 'live',
            'status_display' => "27'",
            'is_live' => true,
            'home_score' => 0,
            'away_score' => 0,
            'live_minute' => 27,
        ]);
        $unsupported = $this->competition('unsupported-league', 'Desteklenmeyen Lig', 'diger-lig', 50);
        $this->match($unsupported, 'Gizli Ev', 'Gizli Dep');

        Http::fake();
        $response = $this->get(route('matches.index'))->assertOk()
            ->assertSee('today-scores-ribbon', false)
            ->assertSee('data-polling="on"', false)
            ->assertSee(route('matches.show', $live), false)
            ->assertSee(route('matches.show', $upcoming), false)
            ->assertSee(route('matches.show', $finished), false)
            ->assertDontSee('Desteklenmeyen Lig')
            ->assertDontSee('Gizli Ev');

        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Gelecek Ev'), strpos($html, 'Canlı Ev'));
        $this->assertLessThan(strpos($html, 'Biten Ev'), strpos($html, 'Gelecek Ev'));
        Http::assertNothingSent();
    }

    public function test_ribbon_is_not_rendered_without_supported_matches_today(): void
    {
        $this->match($this->competitions['super'], 'Yarın Ev', 'Yarın Dep', [
            'kickoff_at' => '2026-09-10 21:00:00',
            'status' => 'finished',
        ]);

        $this->get(route('matches.index'))
            ->assertOk()
            ->assertDontSee('today-scores-ribbon', false);
    }

    public function test_today_uses_istanbul_day_boundaries_and_finished_matches_remain_until_day_end(): void
    {
        $before = $this->match($this->competitions['super'], 'Önceki Gün', 'Rakip', [
            'kickoff_at' => '2026-09-08 20:59:59',
            'status' => 'finished',
        ]);
        $start = $this->match($this->competitions['super'], 'Gün Başlangıcı', 'Rakip', [
            'kickoff_at' => '2026-09-08 21:00:00',
            'status' => 'finished',
            'home_score' => 1,
            'away_score' => 0,
        ]);
        $end = $this->match($this->competitions['super'], 'Gün Sonu', 'Rakip', [
            'kickoff_at' => '2026-09-09 20:59:59',
            'status' => 'finished',
        ]);
        $after = $this->match($this->competitions['super'], 'Ertesi Gün', 'Rakip', [
            'kickoff_at' => '2026-09-09 21:00:00',
            'status' => 'finished',
        ]);

        $this->getJson(route('matches.today.state'))
            ->assertOk()
            ->assertJsonCount(2, 'matches')
            ->assertJsonFragment(['id' => $start->id, 'status_label' => 'MS'])
            ->assertJsonFragment(['id' => $end->id])
            ->assertJsonMissing(['id' => $before->id])
            ->assertJsonMissing(['id' => $after->id]);
    }

    public function test_polling_only_starts_for_live_matches_and_is_disabled_in_match_center(): void
    {
        $scheduled = $this->match($this->competitions['super'], 'Planlı Ev', 'Planlı Dep');

        $this->get(route('matches.index'))
            ->assertOk()
            ->assertSee('data-polling="off"', false);

        $scheduled->update([
            'status' => 'live',
            'status_display' => 'Devre Arası',
            'is_live' => true,
            'home_score' => 1,
            'away_score' => 1,
        ]);

        $this->get(route('matches.index'))
            ->assertOk()
            ->assertSee('data-polling="on"', false);

        $this->get(route('matches.show', $scheduled))
            ->assertOk()
            ->assertSee('data-polling="off"', false);

        $this->getJson(route('matches.today.state'))
            ->assertOk()
            ->assertJsonPath('matches.0.status_label', 'DEVRE')
            ->assertJsonPath('matches.0.is_live', true);
    }

    public function test_matches_page_has_four_provider_id_tabs_filters_and_empty_state(): void
    {
        $superMatch = $this->match($this->competitions['super'], 'Lig Ev', 'Lig Dep');
        $championsMatch = $this->match($this->competitions['champions'], 'ŞL Ev', 'ŞL Dep');

        $default = $this->get(route('matches.index'))
            ->assertOk()
            ->assertSee('matches-competition-tabs', false)
            ->assertSee('Trendyol Süper Lig')
            ->assertSee('Şampiyonlar Ligi')
            ->assertSee('Avrupa Ligi')
            ->assertSee('Konferans Ligi')
            ->assertSee(route('matches.show', $superMatch), false);
        $this->assertStringNotContainsString('ŞL Ev', strstr($default->getContent(), '<div class="feed-column matches-today-page'));

        $champions = $this->get(route('matches.index', ['competition' => FootballCompetition::CHAMPIONS_LEAGUE_PROVIDER_ID]))
            ->assertOk()
            ->assertSee('ŞL Ev')
            ->assertSee(route('matches.show', $championsMatch), false);
        $this->assertStringNotContainsString('Lig Ev', strstr($champions->getContent(), '<div class="feed-column matches-today-page'));

        $this->get(route('matches.index', ['competition' => FootballCompetition::EUROPA_LEAGUE_PROVIDER_ID]))
            ->assertOk()
            ->assertSee('Bugün bu organizasyonda maç bulunmuyor.');
    }

    public function test_global_score_query_eager_loads_teams_and_never_calls_the_provider(): void
    {
        $this->match($this->competitions['super'], 'Eager Ev', 'Eager Dep', [
            'status' => 'live',
            'is_live' => true,
        ]);
        Http::fake();
        Model::preventLazyLoading();

        try {
            $this->get(route('matches.index'))->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }

        Http::assertNothingSent();
    }

    public function test_matches_page_sorts_live_then_upcoming_then_finished_and_excludes_other_days(): void
    {
        $finished = $this->match($this->competitions['super'], 'Tamamlanan Ev', 'Tamamlanan Dep', [
            'kickoff_at' => '2026-09-09 09:00:00',
            'status' => 'finished',
            'home_score' => 2,
            'away_score' => 1,
        ]);
        $upcoming = $this->match($this->competitions['super'], 'Planlanan Ev', 'Planlanan Dep', [
            'kickoff_at' => '2026-09-09 17:00:00',
        ]);
        $live = $this->match($this->competitions['super'], 'Oynanan Ev', 'Oynanan Dep', [
            'kickoff_at' => '2026-09-09 10:00:00',
            'status' => 'live',
            'is_live' => true,
            'home_score' => 0,
            'away_score' => 0,
        ]);
        $otherDay = $this->match($this->competitions['super'], 'Yarınki Ev', 'Yarınki Dep', [
            'kickoff_at' => '2026-09-10 17:00:00',
        ]);

        $page = strstr($this->get(route('matches.index'))->assertOk()->getContent(), '<div class="feed-column matches-today-page');

        $this->assertLessThan(strpos($page, route('matches.show', $upcoming)), strpos($page, route('matches.show', $live)));
        $this->assertLessThan(strpos($page, route('matches.show', $finished)), strpos($page, route('matches.show', $upcoming)));
        $this->assertStringNotContainsString(route('matches.show', $otherDay), $page);
        $this->assertStringContainsString('today-match-group-title is-live', $page);
        $this->assertStringContainsString('today-finished-title', $page);
    }

    public function test_completed_score_remains_today_but_disappears_the_next_istanbul_day(): void
    {
        $match = $this->match($this->competitions['super'], 'Kalıcı Skor Ev', 'Kalıcı Skor Dep', [
            'kickoff_at' => '2026-09-09 10:00:00',
            'status' => 'finished',
            'home_score' => 3,
            'away_score' => 1,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-09 20:59:00', 'UTC'));
        $this->getJson(route('matches.today.state'))
            ->assertOk()
            ->assertJsonFragment(['id' => $match->id, 'status_label' => 'MS']);

        Carbon::setTestNow(Carbon::parse('2026-09-09 21:01:00', 'UTC'));
        $this->getJson(route('matches.today.state'))
            ->assertOk()
            ->assertJsonCount(0, 'matches');
    }

    private function competition(string $providerId, string $name, string $slug, int $sortOrder): FootballCompetition
    {
        return FootballCompetition::create([
            'provider' => 'live-football-api',
            'provider_league_id' => $providerId,
            'name' => $name,
            'display_name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    private function match(FootballCompetition $competition, string $homeName, string $awayName, array $attributes = []): FootballMatch
    {
        static $sequence = 0;
        $sequence++;

        $home = FootballTeam::create([
            'provider' => 'live-football-api',
            'provider_team_id' => 'today-home-'.$sequence,
            'provider_name' => $homeName,
            'display_name' => $homeName,
            'is_active' => true,
        ]);
        $away = FootballTeam::create([
            'provider' => 'live-football-api',
            'provider_team_id' => 'today-away-'.$sequence,
            'provider_name' => $awayName,
            'display_name' => $awayName,
            'is_active' => true,
        ]);

        return FootballMatch::create(array_merge([
            'competition_id' => $competition->id,
            'home_football_team_id' => $home->id,
            'away_football_team_id' => $away->id,
            'provider' => 'live-football-api',
            'provider_match_id' => 'today-match-'.$sequence,
            'kickoff_at' => '2026-09-09 16:00:00',
            'status' => 'scheduled',
            'status_display' => 'Başlamadı',
            'is_live' => false,
        ], $attributes));
    }
}
