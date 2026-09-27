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
            'nations' => $this->competition(FootballCompetition::NATIONS_LEAGUE_PROVIDER_ID, 'Uluslar Ligi', 'uluslar-ligi', 15),
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

    public function test_global_score_ribbon_is_removed_while_today_state_keeps_state_order(): void
    {
        $finished = $this->match($this->competitions['super'], 'Biten Ev', 'Biten Dep', [
            'kickoff_at' => '2026-09-09 09:00:00', 'status' => 'finished', 'home_score' => 2, 'away_score' => 1,
        ]);
        $upcoming = $this->match($this->competitions['super'], 'Gelecek Ev', 'Gelecek Dep', [
            'kickoff_at' => '2026-09-09 17:00:00',
        ]);
        $live = $this->match($this->competitions['super'], 'Canlı Ev', 'Canlı Dep', [
            'kickoff_at' => '2026-09-09 10:00:00', 'status' => 'live', 'status_display' => "27'",
            'is_live' => true, 'home_score' => 0, 'away_score' => 0, 'live_minute' => 27,
        ]);
        $unsupported = $this->competition('unsupported-league', 'Desteklenmeyen Lig', 'diger-lig', 60);
        $unsupportedMatch = $this->match($unsupported, 'Gizli Ev', 'Gizli Dep');

        Http::fake();
        $this->get(route('home'))->assertOk()->assertDontSee('today-scores-ribbon', false);
        $this->get(route('matches.index'))->assertOk()->assertDontSee('today-scores-ribbon', false);
        $this->getJson(route('matches.today.state'))
            ->assertOk()
            ->assertJsonPath('matches.0.id', $live->id)
            ->assertJsonPath('matches.1.id', $upcoming->id)
            ->assertJsonPath('matches.2.id', $finished->id)
            ->assertJsonMissing(['id' => $unsupportedMatch->id]);
        Http::assertNothingSent();
    }

    public function test_today_state_uses_istanbul_day_boundaries(): void
    {
        $before = $this->match($this->competitions['super'], 'Önceki Gün', 'Rakip', [
            'kickoff_at' => '2026-09-08 20:59:59', 'status' => 'finished',
        ]);
        $start = $this->match($this->competitions['super'], 'Gün Başlangıcı', 'Rakip', [
            'kickoff_at' => '2026-09-08 21:00:00', 'status' => 'finished', 'home_score' => 1, 'away_score' => 0,
        ]);
        $end = $this->match($this->competitions['super'], 'Gün Sonu', 'Rakip', [
            'kickoff_at' => '2026-09-09 20:59:59', 'status' => 'finished',
        ]);
        $after = $this->match($this->competitions['super'], 'Ertesi Gün', 'Rakip', [
            'kickoff_at' => '2026-09-09 21:00:00', 'status' => 'finished',
        ]);

        $this->getJson(route('matches.today.state'))
            ->assertOk()
            ->assertJsonCount(2, 'matches')
            ->assertJsonFragment(['id' => $start->id, 'status_label' => 'MS'])
            ->assertJsonFragment(['id' => $end->id])
            ->assertJsonMissing(['id' => $before->id])
            ->assertJsonMissing(['id' => $after->id]);
    }

    public function test_today_state_exposes_polling_state_without_rendering_a_global_ribbon(): void
    {
        $scheduled = $this->match($this->competitions['super'], 'Planlı Ev', 'Planlı Dep');

        $this->get(route('home'))->assertOk()->assertDontSee('today-scores-ribbon', false);
        $this->getJson(route('matches.today.state'))
            ->assertOk()
            ->assertJsonPath('matches.0.polling_active', false)
            ->assertJsonPath('matches.0.is_terminal', false);

        $scheduled->update([
            'status' => 'live', 'status_display' => 'Devre Arası', 'is_live' => true,
            'home_score' => 1, 'away_score' => 1,
        ]);

        $this->get(route('home'))->assertOk()->assertDontSee('today-scores-ribbon', false);
        $this->get(route('matches.show', $scheduled))->assertOk()->assertDontSee('today-scores-ribbon', false);
        $this->getJson(route('matches.today.state'))
            ->assertOk()
            ->assertJsonPath('matches.0.status_label', 'DEVRE')
            ->assertJsonPath('matches.0.is_live', true);
    }

    public function test_global_score_and_match_page_queries_eager_load_teams_without_provider_requests(): void
    {
        $this->match($this->competitions['super'], 'Eager Ev', 'Eager Dep', ['status' => 'live', 'is_live' => true]);
        Http::fake();
        Model::preventLazyLoading();

        try {
            $this->get(route('home'))->assertOk();
            $this->get(route('matches.index'))->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }

        Http::assertNothingSent();
    }

    public function test_completed_score_remains_until_the_istanbul_day_ends(): void
    {
        $match = $this->match($this->competitions['super'], 'Kalıcı Skor Ev', 'Kalıcı Skor Dep', [
            'kickoff_at' => '2026-09-09 10:00:00', 'status' => 'finished', 'home_score' => 3, 'away_score' => 1,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-09 20:59:00', 'UTC'));
        $this->getJson(route('matches.today.state'))
            ->assertOk()
            ->assertJsonFragment(['id' => $match->id, 'status_label' => 'MS']);

        Carbon::setTestNow(Carbon::parse('2026-09-09 21:01:00', 'UTC'));
        $this->getJson(route('matches.today.state'))->assertOk()->assertJsonCount(0, 'matches');
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
            'provider' => 'live-football-api', 'provider_team_id' => 'today-home-'.$sequence,
            'provider_name' => $homeName, 'display_name' => $homeName, 'is_active' => true,
        ]);
        $away = FootballTeam::create([
            'provider' => 'live-football-api', 'provider_team_id' => 'today-away-'.$sequence,
            'provider_name' => $awayName, 'display_name' => $awayName, 'is_active' => true,
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
