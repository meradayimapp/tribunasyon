<?php

namespace Tests\Feature\Football;

use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use App\Models\Player;
use App\Services\Football\FootballLiveSynchronizer;
use App\Services\Football\MatchFormationLayout;
use App\Services\Football\PlayerPositionFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchCenterRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('services.live_football_api', [
            'key' => 'private-match-key', 'base_url' => 'https://football.test/api/v1',
        ]);
    }

    public function test_six_tabs_render_in_order_and_all_sections_have_independent_fallbacks(): void
    {
        [$match] = $this->match();
        $this->fakeStatic();

        $html = $this->get(route('matches.show', $match))->assertOk()
            ->assertSee('Maç istatistikleri henüz mevcut değil.')
            ->assertSee('Kadro bilgisi henüz açıklanmadı.')
            ->assertSee('Puan durumu şu anda görüntülenemiyor.')
            ->assertSee('Karşılaştırma verisi bulunamadı.')
            ->assertDontSee('private-match-key')
            ->getContent();

        $tabs = ['Sohbet', 'Özet', 'İstatistik', 'Kadro', 'Puan Durumu', 'H2H'];
        $last = -1;
        foreach ($tabs as $tab) {
            $position = strpos($html, 'role="tab"', $last + 1);
            $this->assertNotFalse($position);
            $this->assertStringContainsString($tab, substr($html, $position, 400));
            $this->assertGreaterThan($last, $position);
            $last = $position;
        }
        $this->assertStringContainsString('matchLiveState(', $html);
        $this->assertStringContainsString('match-panel-sohbet', $html);
        $this->assertStringContainsString('match-panel-ozet', $html);
    }

    public function test_first_view_fetches_static_data_once_and_repeat_views_use_the_same_caches(): void
    {
        [$match] = $this->match();
        $this->fakeStatic(standings: [
            $this->standingRow('provider-away', 'Ev Sahibi', 1),
            $this->standingRow('provider-home', 'Rakip', 2),
            $this->standingRow('provider-third', 'Ev Sahibi', 3),
        ], h2h: [
            'home_form' => [$this->pastMatch('provider-home', 'provider-third', '2-1')],
            'away_form' => [$this->pastMatch('provider-away', 'provider-third', '0-0')],
            'h2h' => [$this->pastMatch('provider-home', 'provider-away', '3-1')],
            'h2h_summary' => ['home_wins' => 4, 'draws' => 2, 'away_wins' => 1],
        ], injuries: ['home' => [['name' => 'Gerçek Oyuncu', 'status' => 'Suspended']], 'away' => []]);

        $response = $this->get(route('matches.show', $match))->assertOk()
            ->assertSee('Gerçek Oyuncu')
            ->assertSee('Suspended')
            ->assertSee('Son Karşılaşmalar')
            ->assertSee('Toplam')
            ->assertDontSee('private-match-key');
        $html = $response->getContent();
        $this->assertSame(2, substr_count($html, 'data-current-team="true"'));
        $this->assertMatchesRegularExpression('/is-current-team[^>]*>.*?Rakip/s', $html);
        $this->assertMatchesRegularExpression('/is-current-team[^>]*>.*?Ev Sahibi/s', $html);
        Http::assertSentCount(3);

        $this->get(route('matches.show', $match))->assertOk();
        Http::assertSentCount(3);
        $this->assertNotNull(Cache::get('football:standings:league-1:current'));
        $this->assertNotNull(Cache::get('football:match:h2h:live-football-api:match-1'));
        $this->assertNotNull(Cache::get('football:match:injuries:live-football-api:match-1'));
    }

    public function test_malformed_and_unavailable_static_responses_do_not_break_match_center(): void
    {
        [$match] = $this->match();
        Http::fake(['*' => Http::response(['success' => false, 'data' => []], 429)]);

        $this->get(route('matches.show', $match))->assertOk()
            ->assertSee('Puan durumu şu anda görüntülenemiyor.')
            ->assertSee('Karşılaştırma verisi bulunamadı.')
            ->assertDontSee('private-match-key');
        $this->get(route('matches.show', $match))->assertOk();
        $this->assertLessThanOrEqual(3, Http::recorded()->count());

        Cache::flush();
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['match_id' => 'wrong', 'h2h' => 'broken']])]);
        $this->get(route('matches.show', $match))->assertOk()
            ->assertSee('Karşılaştırma verisi bulunamadı.');
    }

    public function test_normalized_live_events_stats_and_lineup_rating_reach_view_and_database_state(): void
    {
        [$match] = $this->match(['status' => 'live', 'is_live' => true, 'status_display' => 'CANLI']);
        Http::fake([
            'football.test/api/v1/live_match_details*' => Http::response(['success' => true, 'data' => [
                'match_id' => 'match-1',
                'header' => ['home' => ['score' => '1'], 'away' => ['score' => '0'], 'status' => ['is_live' => true, 'minute' => '27']],
                'events' => [
                    ['time' => '26', 'type' => 'Substitution', 'side' => 'home', 'detail' => ['player_in' => ['name' => 'Giren'], 'player_out' => ['name' => 'Çıkan']]],
                    ['time' => '17', 'type' => 'Goal', 'side' => 'home', 'detail' => ['player' => ['name' => 'Golcü'], 'is_penalty' => true]],
                    ['time' => '25', 'type' => 'Red Card', 'side' => 'away', 'detail' => ['player' => ['name' => 'Kartlı'], 'is_second_yellow' => true]],
                ],
                'stats' => [['label' => 'Possession', 'home' => '40%', 'away' => '60%'], ['label' => 'Shots', 'home' => '2', 'away' => '3']],
            ]]),
            'football.test/api/v1/lineups*' => Http::response(['success' => true, 'data' => [
                'match_id' => 'match-1', 'home' => ['starting' => [['name' => 'Kaleci', 'position' => 'Goalkeeper', 'rating' => 7.4]], 'subs' => [['name' => 'Yedek', 'number' => '12']], 'coach' => ['name' => 'Gerçek Hoca']],
                'away' => ['starting' => [['name' => 'Rakip Oyuncu', 'rating' => null]], 'subs' => []],
                'formation' => ['home' => 433, 'away' => 442], 'is_projected' => false,
            ]]),
            'football.test/api/v1/league_standings*' => Http::response(['success' => true, 'data' => ['league_id' => 'league-1', 'standings' => []]]),
            'football.test/api/v1/h2h*' => Http::response(['success' => true, 'data' => ['match_id' => 'match-1', 'home_form' => [], 'away_form' => [], 'h2h' => []]]),
            'football.test/api/v1/injuries*' => Http::response(['success' => true, 'data' => ['match_id' => 'match-1', 'injuries' => ['home' => [], 'away' => []]]]),
        ]);

        app(FootballLiveSynchronizer::class)->syncDetails($match);
        app(FootballLiveSynchronizer::class)->syncLineups($match->fresh());
        $match->refresh();
        $this->assertSame('penalty_goal', $match->live_events[0]['type']);
        $this->assertSame('second_yellow', $match->live_events[1]['type']);
        $this->assertSame('substitution', $match->live_events[2]['type']);
        $this->assertEquals(40, $match->match_stats[0]['home_share']);
        $this->assertSame(7.4, $match->lineups['home']['starting'][0]['rating']);
        $this->assertArrayNotHasKey('rating', $match->lineups['away']['starting'][0]);

        $this->get(route('matches.show', $match))->assertOk()
            ->assertSee('Penaltı Golü')->assertSee('İkinci Sarı Kart')->assertSee('Giren')
            ->assertSee('Topa Sahip Olma')->assertSee('40%')->assertSee('7.4')
            ->assertSee('Yedek')->assertSee('Gerçek Hoca');
        $this->getJson(route('matches.state', $match))->assertOk()
            ->assertJsonPath('events.0.type', 'penalty_goal')
            ->assertJsonPath('stats.0.home_share', 40)
            ->assertJsonPath('lineups.home.starting.0.rating', 7.4);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/live_match_details'));
    }

    public function test_formation_pitch_requires_complete_ordered_eleven_otherwise_falls_back_to_list(): void
    {
        $players = array_merge(
            [['name' => 'Kaleci', 'position' => 'Goalkeeper']],
            array_fill(0, 4, ['name' => 'Savunmacı', 'position' => 'Defender']),
            array_fill(0, 3, ['name' => 'Orta Saha', 'position' => 'Midfielder']),
            array_fill(0, 3, ['name' => 'Forvet', 'position' => 'Forward']),
        );
        $this->assertCount(4, MatchFormationLayout::rows(433, $players));
        $fourTwoThreeOne = array_merge(
            [['name' => 'Kaleci', 'position' => 'Goalkeeper']],
            array_fill(0, 4, ['name' => 'Savunmacı', 'position' => 'Defender']),
            array_fill(0, 5, ['name' => 'Orta Saha', 'position' => 'Midfielder']),
            [['name' => 'Forvet', 'position' => 'Forward']],
        );
        $this->assertCount(5, MatchFormationLayout::rows('4-2-3-1', $fourTwoThreeOne));
        $providerFourFourTwo = array_merge(
            [['name' => 'Kaleci', 'position' => 'Goalkeeper']],
            array_fill(0, 4, ['name' => 'Savunmacı', 'position' => 'Defender']),
            array_fill(0, 3, ['name' => 'Orta Saha', 'position' => 'Midfielder']),
            array_fill(0, 3, ['name' => 'Hücumcu', 'position' => 'Attacker']),
        );
        $this->assertCount(4, MatchFormationLayout::rows(442, $providerFourFourTwo));
        $this->assertNull(MatchFormationLayout::rows('4-4-3', $players));
        $this->assertNull(MatchFormationLayout::rows('4-3-3', array_slice($players, 0, 10)));
        $players[0]['position'] = 'Forward';
        $this->assertNull(MatchFormationLayout::rows('4-3-3', $players));
    }

    public function test_complete_provider_ordered_lineup_renders_pitch_and_live_chat_is_default(): void
    {
        $players = array_merge(
            [['id' => 'keeper-1', 'name' => 'Kaleci', 'position' => 'Goalkeeper', 'number' => '1']],
            array_fill(0, 4, ['name' => 'Savunmacı', 'position' => 'Defender']),
            array_fill(0, 3, ['name' => 'Orta Saha', 'position' => 'Midfielder']),
            array_fill(0, 3, ['name' => 'Forvet', 'position' => 'Forward']),
        );
        [$match] = $this->match([
            'status' => 'live', 'is_live' => true, 'status_display' => 'CANLI',
            'lineups' => ['home' => ['starting' => $players, 'subs' => []], 'away' => ['starting' => [], 'subs' => []], 'formation' => ['home' => 433]],
        ]);
        $keeper = Player::factory()->create(['provider_player_id' => 'keeper-1', 'slug' => 'saha-kalecisi']);
        $this->fakeStatic();

        $html = $this->get(route('matches.show', $match))->assertOk()
            ->assertSee('match-lineup-pitch', false)->assertSee('Canlı Maç Sohbeti')
            ->assertSee(route('players.show', $keeper), false)->getContent();
        $this->assertStringContainsString('matchLiveState(', $html);
        $this->assertMatchesRegularExpression('/<a class="match-lineup-pitch-player match-lineup-player-link" href="[^"]*saha-kalecisi"/s', $html);
        $this->assertMatchesRegularExpression('/id="match-panel-sohbet"[^>]*x-show="activeTab === \'sohbet\'"[^>]*>/s', $html);
    }

    public function test_lineup_uses_only_provider_player_id_for_profile_links_and_one_bulk_lookup(): void
    {
        $linked = Player::factory()->create([
            'name' => 'Yerel İsim', 'slug' => 'yerel-oyuncu', 'provider_player_id' => 'player-42',
            'photo_path' => 'players/photos/manual.png', 'provider_image_url' => 'https://cdn.test/ignored.png',
        ]);
        $sameNameButDifferentId = Player::factory()->create([
            'name' => 'Aynı İsim', 'slug' => 'yanlis-oyuncu', 'provider_player_id' => 'different-id',
        ]);
        [$match] = $this->match(['lineups' => [
            'home' => ['starting' => [
                ['id' => 'player-42', 'name' => 'API İsim', 'number' => '1', 'position' => 'Goalkeeper', 'image' => 'https://cdn.test/api.png'],
                ['id' => 'unlinked-id', 'name' => 'Aynı İsim', 'number' => '2', 'position' => 'Defender'],
                ['id' => 'third-id', 'name' => 'Diğer', 'position' => '<b>Wing Back</b>'],
            ], 'subs' => [['id' => 'player-42', 'name' => 'API İsim', 'position' => 'Forward']]],
            'away' => ['starting' => [], 'subs' => []],
        ]]);
        $this->fakeStatic();

        DB::enableQueryLog();
        $html = $this->get(route('matches.show', $match))->assertOk()
            ->assertSee(route('players.show', $linked), false)
            ->assertSee('Kaleci')->assertSee('Defans')->assertSee('Forvet')
            ->assertSee('Wing Back')->assertDontSee('<b>Wing Back</b>', false)
            ->assertSee('manual.png', false)->assertDontSee('https://cdn.test/ignored.png', false)
            ->assertDontSee(route('players.show', $sameNameButDifferentId), false)
            ->getContent();
        $this->assertMatchesRegularExpression('/<a class="match-lineup-player-link" href="[^"]*yerel-oyuncu"[^>]*>.*?API İsim/s', $html);
        $this->assertMatchesRegularExpression('/<div>.*?Aynı İsim/s', $html);

        $bulkQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => preg_match('/from ["`]?players["`]?/i', $query['query'])
            && str_contains($query['query'], 'provider_player_id'));
        $this->assertCount(1, $bulkQueries);
        DB::disableQueryLog();

        $this->getJson(route('matches.state', $match))->assertOk()
            ->assertJsonPath('lineups.home.starting.0.profile_url', route('players.show', $linked))
            ->assertJsonPath('lineups.home.starting.0.position_display', 'Kaleci')
            ->assertJsonPath('lineups.home.starting.1.position_display', 'Defans')
            ->assertJsonMissingPath('lineups.home.starting.1.profile_url')
            ->assertJsonMissingPath('lineups.home.starting.2.profile_url');
    }

    public function test_shared_position_formatter_handles_abbreviations_and_unsafe_unknowns(): void
    {
        foreach (['Goalkeeper' => 'Kaleci', 'GK' => 'Kaleci', 'Defender' => 'Defans', 'DF' => 'Defans',
            'Midfielder' => 'Orta saha', 'MF' => 'Orta saha', 'Attacker' => 'Forvet',
            'Forward' => 'Forvet', 'FW' => 'Forvet'] as $input => $expected) {
            $this->assertSame($expected, PlayerPositionFormatter::format($input));
        }
        $this->assertSame('Wing Back', PlayerPositionFormatter::format('<script>bad</script>Wing Back'));
        $this->assertNull(PlayerPositionFormatter::format(null));
    }

    public function test_503_uses_stale_supplement_data_without_retrying_static_endpoints(): void
    {
        [$match] = $this->match();
        Cache::put('football:match:h2h:live-football-api:match-1:stale', [
            'home_form' => ['W'], 'away_form' => [], 'history' => [], 'summary' => null,
        ], now()->addHour());
        Cache::put('football:match:injuries:live-football-api:match-1:stale', [
            'home' => [['name' => 'Cached Oyuncu', 'status' => 'Suspended', 'position' => null, 'image' => null]], 'away' => [],
        ], now()->addHour());
        Http::fake(['*' => Http::response(['success' => false], 503)]);

        $this->get(route('matches.show', $match))->assertOk()
            ->assertSee('Cached Oyuncu')->assertSee('Son 5 Form');
        $sent = Http::recorded();
        $this->assertSame(1, $sent->filter(fn (array $entry): bool => str_contains($entry[0]->url(), '/h2h'))->count());
        $this->assertSame(1, $sent->filter(fn (array $entry): bool => str_contains($entry[0]->url(), '/injuries'))->count());
    }

    public function test_state_endpoint_uses_only_database_and_keeps_existing_contract_keys(): void
    {
        [$match] = $this->match(['status' => 'finished', 'home_score' => 2, 'away_score' => 1,
            'match_stats' => [['label' => 'Şut', 'home' => '2', 'away' => '1', 'home_share' => 66.67]],
            'lineups' => ['home' => ['starting' => [['name' => 'Oyuncu']]]],
        ]);
        Http::fake();

        $this->getJson(route('matches.state', $match))->assertOk()
            ->assertJsonPath('is_finished', true)
            ->assertJsonPath('score.home', 2)
            ->assertJsonPath('stats.0.label', 'Şut')
            ->assertJsonPath('lineups.home.starting.0.name', 'Oyuncu');
        Http::assertNothingSent();
    }

    public function test_malformed_database_detail_rows_are_filtered_without_500(): void
    {
        [$match] = $this->match([
            'live_events' => ['bad', null],
            'match_stats' => ['bad'],
            'lineups' => ['home' => ['starting' => ['bad'], 'subs' => []]],
        ]);
        $this->fakeStatic();

        $this->get(route('matches.show', $match))->assertOk()
            ->assertSee('Henüz önemli bir olay yok.')
            ->assertSee('Maç istatistikleri henüz mevcut değil.')
            ->assertSee('Kadro bilgisi henüz açıklanmadı.');
        $this->getJson(route('matches.state', $match))->assertOk()
            ->assertJsonPath('events', [])
            ->assertJsonPath('stats', [])
            ->assertJsonPath('lineups.home.starting', []);
    }

    private function match(array $overrides = []): array
    {
        $competition = FootballCompetition::create(['provider' => 'live-football-api', 'provider_league_id' => 'league-1', 'name' => 'Test Ligi', 'slug' => 'test-ligi', 'is_active' => true]);
        $home = FootballTeam::create(['provider' => 'live-football-api', 'provider_team_id' => 'provider-home', 'provider_name' => 'Ev Sahibi', 'is_active' => true]);
        $away = FootballTeam::create(['provider' => 'live-football-api', 'provider_team_id' => 'provider-away', 'provider_name' => 'Rakip', 'is_active' => true]);
        $match = FootballMatch::create(array_merge([
            'competition_id' => $competition->id, 'home_football_team_id' => $home->id, 'away_football_team_id' => $away->id,
            'provider' => 'live-football-api', 'provider_match_id' => 'match-1', 'kickoff_at' => '2026-09-09 17:00:00',
            'status' => 'scheduled', 'is_live' => false, 'status_display' => 'Başlamadı',
        ], $overrides));

        return [$match, $home, $away];
    }

    private function fakeStatic(array $standings = [], array $h2h = [], array $injuries = []): void
    {
        Http::fake([
            'football.test/api/v1/league_standings*' => Http::response(['success' => true, 'data' => ['league_id' => 'league-1', 'season' => '2026/27', 'standings' => [['title' => 'Test Ligi', 'table' => $standings]]]]),
            'football.test/api/v1/h2h*' => Http::response(['success' => true, 'data' => ['match_id' => 'match-1', 'home_form' => $h2h['home_form'] ?? [], 'away_form' => $h2h['away_form'] ?? [], 'h2h' => $h2h['h2h'] ?? [], 'h2h_summary' => $h2h['h2h_summary'] ?? null]]),
            'football.test/api/v1/injuries*' => Http::response(['success' => true, 'data' => ['match_id' => 'match-1', 'injuries' => ['home' => $injuries['home'] ?? [], 'away' => $injuries['away'] ?? []]]]),
        ]);
    }

    private function standingRow(string $id, string $name, int $rank): array
    {
        return ['rank' => $rank, 'team' => ['id' => $id, 'name' => $name], 'played' => 5, 'won' => 2, 'drawn' => 1, 'lost' => 2, 'goals_for' => 7, 'goals_against' => 6, 'goal_diff' => 1, 'points' => 7];
    }

    private function pastMatch(string $homeId, string $awayId, string $score): array
    {
        return ['date' => '2026-08-01', 'home' => ['id' => $homeId, 'name' => 'Gerçek Ev'], 'away' => ['id' => $awayId, 'name' => 'Gerçek Rakip'], 'score' => $score];
    }
}
