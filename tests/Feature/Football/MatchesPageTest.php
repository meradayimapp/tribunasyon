<?php

namespace Tests\Feature\Football;

use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use App\Services\Football\MatchesPageService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 12:00:00', FootballMatch::DISPLAY_TIMEZONE));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_date_navigation_uses_istanbul_day_boundaries_for_today_past_and_future(): void
    {
        $competition = $this->competition();
        $start = $this->match($competition, 'Gün Başlangıcı', 'Rakip A', '2026-09-23 21:00:00');
        $end = $this->match($competition, 'Gün Sonu', 'Rakip B', '2026-09-24 20:59:59');
        $before = $this->match($competition, 'Önceki Gün', 'Rakip C', '2026-09-23 20:59:59');
        $after = $this->match($competition, 'Sonraki Gün', 'Rakip D', '2026-09-24 21:00:00');

        $today = $this->get(route('matches.index', ['date' => '2026-09-24']))
            ->assertOk()
            ->assertSee('value="2026-09-24"', false)
            ->assertSee('Bugün')
            ->assertSee(route('matches.show', $start), false)
            ->assertSee(route('matches.show', $end), false)
            ->assertDontSee(route('matches.show', $before), false)
            ->assertDontSee(route('matches.show', $after), false);

        $this->assertStringContainsString('aria-current="date"', $today->getContent());

        $this->get(route('matches.index', ['date' => '2026-09-23']))
            ->assertOk()
            ->assertSee(route('matches.show', $before), false)
            ->assertDontSee(route('matches.show', $start), false);

        $this->get(route('matches.index', ['date' => '2026-09-25']))
            ->assertOk()
            ->assertSee(route('matches.show', $after), false)
            ->assertDontSee(route('matches.show', $end), false);
    }

    public function test_invalid_date_redirects_to_the_current_istanbul_date_and_preserves_valid_filters(): void
    {
        $this->get(route('matches.index', [
            'date' => '2026-02-31',
            'status' => 'live',
            'competition' => 'premier-league',
        ]))->assertRedirect(route('matches.index', [
            'date' => '2026-09-24',
            'status' => 'live',
            'competition' => 'premier-league',
        ]));
    }

    public function test_status_and_slug_competition_filters_are_applied_in_the_database_query(): void
    {
        $superLeague = $this->competition();
        $premierLeague = $this->competition([
            'provider_league_id' => FootballCompetition::PREMIER_LEAGUE_PROVIDER_ID,
            'name' => 'Premier Lig',
            'display_name' => 'Premier League',
            'slug' => 'premier-league',
            'country' => 'İngiltere',
            'sort_order' => 50,
        ]);

        $live = $this->match($superLeague, 'Canlı Ev', 'Canlı Dep', '2026-09-24 16:00:00', [
            'status' => 'live', 'is_live' => true, 'home_score' => 1, 'away_score' => 0, 'live_minute' => 63,
        ]);
        $upcoming = $this->match($superLeague, 'Yaklaşan Ev', 'Yaklaşan Dep', '2026-09-24 17:00:00');
        $finished = $this->match($superLeague, 'Biten Ev', 'Biten Dep', '2026-09-24 14:00:00', [
            'status' => 'finished', 'home_score' => 2, 'away_score' => 1,
        ]);
        $postponed = $this->match($superLeague, 'Ertelenen Ev', 'Ertelenen Dep', '2026-09-24 15:00:00', [
            'status' => 'postponed', 'status_display' => 'Ertelendi',
        ]);
        $premier = $this->match($premierLeague, 'Arsenal', 'Chelsea', '2026-09-24 18:00:00');

        $this->get(route('matches.index', ['date' => '2026-09-24']))
            ->assertOk()
            ->assertSee(route('matches.show', $live), false)
            ->assertSee(route('matches.show', $upcoming), false)
            ->assertSee(route('matches.show', $finished), false)
            ->assertSee(route('matches.show', $postponed), false)
            ->assertSee(route('matches.show', $premier), false);

        $this->get(route('matches.index', ['date' => '2026-09-24', 'status' => 'live']))
            ->assertOk()
            ->assertSee(route('matches.show', $live), false)
            ->assertDontSee(route('matches.show', $upcoming), false)
            ->assertDontSee(route('matches.show', $finished), false);

        $this->get(route('matches.index', ['date' => '2026-09-24', 'status' => 'upcoming']))
            ->assertOk()
            ->assertSee(route('matches.show', $upcoming), false)
            ->assertSee(route('matches.show', $premier), false)
            ->assertDontSee(route('matches.show', $live), false)
            ->assertDontSee(route('matches.show', $postponed), false);

        $this->get(route('matches.index', ['date' => '2026-09-24', 'status' => 'finished']))
            ->assertOk()
            ->assertSee(route('matches.show', $finished), false)
            ->assertDontSee(route('matches.show', $live), false)
            ->assertDontSee(route('matches.show', $postponed), false);

        $this->get(route('matches.index', [
            'date' => '2026-09-24',
            'status' => 'upcoming',
            'competition' => 'premier-league',
        ]))->assertOk()
            ->assertSee(route('matches.show', $premier), false)
            ->assertDontSee(route('matches.show', $upcoming), false)
            ->assertSee('value="premier-league" selected', false);
    }

    public function test_matches_are_grouped_by_competition_sorted_by_sort_order_and_keep_provider_group_metadata(): void
    {
        $later = $this->competition([
            'provider_league_id' => FootballCompetition::PREMIER_LEAGUE_PROVIDER_ID,
            'name' => 'Premier Lig',
            'display_name' => 'Premier League',
            'slug' => 'premier-league',
            'country' => 'İngiltere',
            'sort_order' => 50,
        ]);
        $first = $this->competition([
            'provider_league_id' => FootballCompetition::NATIONS_LEAGUE_PROVIDER_ID,
            'name' => 'UEFA Nations League',
            'display_name' => 'Uluslar Ligi',
            'slug' => 'uluslar-ligi',
            'country' => null,
            'sort_order' => 15,
        ]);
        $firstMatch = $this->match($first, 'Hollanda', 'Almanya', '2026-09-24 18:45:00', [
            'meta' => ['group' => 'Lig A Grup 2'],
        ]);
        $secondMatch = $this->match($first, 'Sırbistan', 'Yunanistan', '2026-09-24 19:45:00', [
            'meta' => ['group' => 'Lig A Grup 2'],
        ]);
        $premierMatch = $this->match($later, 'Manchester City', 'Chelsea', '2026-09-24 15:30:00');

        $response = $this->get(route('matches.index', ['date' => '2026-09-24']))->assertOk()
            ->assertSee('Avrupa')
            ->assertSee('Lig A Grup 2')
            ->assertSee(route('competitions.show', $first->slug), false)
            ->assertSee(route('matches.show', $firstMatch), false)
            ->assertSee(route('matches.show', $secondMatch), false)
            ->assertSee(route('matches.show', $premierMatch), false);

        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Premier League'), strpos($html, 'Uluslar Ligi'));
        $this->assertSame(1, substr_count($html, 'aria-labelledby="matches-competition-'.$first->id.'"'));
    }

    public function test_compact_rows_render_every_match_state_and_link_the_whole_row_to_the_existing_match_center(): void
    {
        $competition = $this->competition();
        $scheduled = $this->match($competition, 'Planlı Ev', 'Planlı Dep', '2026-09-24 18:00:00');
        $live = $this->match($competition, 'Canlı Ev', 'Canlı Dep', '2026-09-24 16:00:00', [
            'status' => 'live', 'is_live' => true, 'home_score' => 1, 'away_score' => 0, 'live_minute' => 63,
        ]);
        $halfTime = $this->match($competition, 'Devre Ev', 'Devre Dep', '2026-09-24 16:30:00', [
            'status' => 'halftime', 'status_display' => 'Devre Arası', 'is_live' => true, 'home_score' => 1, 'away_score' => 1,
        ]);
        $finished = $this->match($competition, 'Biten Ev', 'Biten Dep', '2026-09-24 13:00:00', [
            'status' => 'finished', 'home_score' => 2, 'away_score' => 1,
        ]);
        $postponed = $this->match($competition, 'Ertelenen Ev', 'Ertelenen Dep', '2026-09-24 14:00:00', [
            'status' => 'postponed', 'status_display' => 'Ertelendi',
        ]);
        $cancelled = $this->match($competition, 'İptal Ev', 'İptal Dep', '2026-09-24 15:00:00', [
            'status' => 'cancelled', 'status_display' => 'İptal',
        ]);

        $this->get(route('matches.index', ['date' => '2026-09-24']))
            ->assertOk()
            ->assertSee('21:00')
            ->assertSee('- : -')
            ->assertSee('Başlamadı')
            ->assertSee("63' CANLI")
            ->assertSee('DEVRE')
            ->assertSee('MS')
            ->assertSee('ERT.')
            ->assertSee('İPT.')
            ->assertSee(route('matches.show', $scheduled), false)
            ->assertSee(route('matches.show', $live), false)
            ->assertSee(route('matches.show', $halfTime), false)
            ->assertSee(route('matches.show', $finished), false)
            ->assertSee(route('matches.show', $postponed), false)
            ->assertSee(route('matches.show', $cancelled), false)
            ->assertSee('matches-list-row', false)
            ->assertDontSee('match-card', false);
    }

    public function test_empty_state_is_compact_query_pages_canonicalize_to_maclar_and_the_global_ticker_is_hidden(): void
    {
        $response = $this->get(route('matches.index', [
            'date' => '2026-09-25',
            'status' => 'finished',
        ]))->assertOk()
            ->assertSee('seçili filtrelerde maç bulunmuyor.')
            ->assertSee('Önceki gün')
            ->assertSee('Sonraki gün')
            ->assertSee('<link rel="canonical" href="'.url('/maclar').'">', false)
            ->assertDontSee('today-scores-ribbon', false)
            ->assertDontSee('Uluslar Ligi Merkezi')
            ->assertDontSee('Tüm fikstür ve puan durumu');

        $this->assertStringNotContainsString('competition-center-link', $response->getContent());
    }

    public function test_match_center_state_endpoint_batches_only_featured_active_matches_and_stops_finished_polling(): void
    {
        $featured = $this->competition();
        $other = $this->competition([
            'provider_league_id' => 'not-featured',
            'name' => 'Diğer Lig',
            'display_name' => 'Diğer Lig',
            'slug' => 'diger-lig',
        ]);
        $live = $this->match($featured, 'Canlı Ev', 'Canlı Dep', '2026-09-24 16:00:00', [
            'status' => 'live', 'is_live' => true, 'home_score' => 1, 'away_score' => 0, 'live_minute' => 71,
        ]);
        $ignored = $this->match($other, 'Gizli Ev', 'Gizli Dep', '2026-09-24 16:00:00', [
            'status' => 'live', 'is_live' => true,
        ]);

        $this->getJson(route('matches.index.state', ['ids' => [$live->id, $ignored->id]]))
            ->assertOk()
            ->assertJsonPath("matches.{$live->id}.minute", 71)
            ->assertJsonPath("matches.{$live->id}.polling_active", true)
            ->assertJsonMissingPath("matches.{$ignored->id}");

        $live->update(['status' => 'finished', 'is_live' => false, 'home_score' => 2, 'away_score' => 0]);

        $this->getJson(route('matches.index.state', ['ids' => [$live->id]]))
            ->assertOk()
            ->assertJsonPath("matches.{$live->id}.status", 'finished')
            ->assertJsonPath("matches.{$live->id}.polling_active", false);
    }

    public function test_match_listing_eager_loads_relations_with_constant_query_count_and_never_calls_provider(): void
    {
        $competition = $this->competition();
        foreach (range(1, 5) as $index) {
            $this->match($competition, "Ev {$index}", "Dep {$index}", "2026-09-24 1{$index}:00:00");
        }

        Http::fake();
        DB::flushQueryLog();
        DB::enableQueryLog();
        Model::preventLazyLoading();

        try {
            $matches = app(MatchesPageService::class)->matches(
                CarbonImmutable::parse('2026-09-24', FootballMatch::DISPLAY_TIMEZONE),
            );

            foreach ($matches as $match) {
                $match->competition->display_name;
                $match->homeTeam->resolved_name;
                $match->homeTeam->logoUrl();
                $match->awayTeam->resolved_name;
                $match->awayTeam->logoUrl();
            }
        } finally {
            Model::preventLazyLoading(false);
        }

        $this->assertLessThanOrEqual(6, count(DB::getQueryLog()));
        Http::assertNothingSent();
    }

    private function competition(array $attributes = []): FootballCompetition
    {
        return FootballCompetition::create(array_merge([
            'provider' => 'live-football-api',
            'provider_league_id' => FootballCompetition::SUPER_LEAGUE_PROVIDER_ID,
            'name' => 'Trendyol Süper Lig',
            'display_name' => 'Trendyol Süper Lig',
            'slug' => 'super-lig',
            'country' => 'Türkiye',
            'timezone' => 'UTC',
            'is_active' => true,
            'sort_order' => 10,
        ], $attributes));
    }

    private function match(
        FootballCompetition $competition,
        string $homeName,
        string $awayName,
        string $kickoffAt,
        array $attributes = [],
    ): FootballMatch {
        static $sequence = 0;
        $sequence++;

        $home = FootballTeam::create([
            'provider' => 'live-football-api',
            'provider_team_id' => 'matches-home-'.$sequence,
            'provider_name' => $homeName,
            'display_name' => $homeName,
            'provider_logo_url' => "https://cdn.test/home-{$sequence}.png",
            'is_active' => true,
        ]);
        $away = FootballTeam::create([
            'provider' => 'live-football-api',
            'provider_team_id' => 'matches-away-'.$sequence,
            'provider_name' => $awayName,
            'display_name' => $awayName,
            'provider_logo_url' => "https://cdn.test/away-{$sequence}.png",
            'is_active' => true,
        ]);

        return FootballMatch::create(array_merge([
            'competition_id' => $competition->id,
            'home_football_team_id' => $home->id,
            'away_football_team_id' => $away->id,
            'provider' => 'live-football-api',
            'provider_match_id' => 'matches-match-'.$sequence,
            'kickoff_at' => $kickoffAt,
            'status' => 'scheduled',
            'status_display' => 'Başlamadı',
            'is_live' => false,
        ], $attributes));
    }
}
