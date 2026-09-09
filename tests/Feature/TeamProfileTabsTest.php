<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\TeamStatus;
use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use App\Models\Player;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamProfileTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-09 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_social_team_feed_remains_available_and_exposes_profile_tabs(): void
    {
        $team = $this->team();
        $author = User::factory()->create();
        $post = Post::create([
            'team_id' => $team->id,
            'created_by' => $author->id,
            'type' => PostType::Text,
            'body' => 'Korunan takım akışı',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);

        $this->get(route('teams.show', $team))
            ->assertOk()
            ->assertSee($post->body)
            ->assertSee(route('teams.show', $team), false)
            ->assertSee(route('teams.fixtures', $team), false)
            ->assertSee(route('teams.players', $team), false)
            ->assertSee('Takım topluluğu');
    }

    public function test_fixture_and_player_tabs_are_public_and_keep_the_social_profile_header(): void
    {
        $team = $this->team();

        $this->get(route('teams.fixtures', $team))->assertOk()->assertSee($team->name)->assertSee('Fikstür');
        $this->get(route('teams.players', $team))->assertOk()->assertSee($team->name)->assertSee('Oyuncular');
    }

    public function test_fixtures_use_team_id_linkage_and_never_provider_name_matching(): void
    {
        $team = $this->team(['name' => 'Galatasaray', 'slug' => 'galatasaray']);
        $competition = $this->competition();
        $linked = $this->footballTeam('completely-different-provider-name', $team);
        $sameNameButUnlinked = $this->footballTeam('Galatasaray');
        $opponent = $this->footballTeam('Rakip');
        $linkedMatch = $this->match($competition, $linked, $opponent, ['provider_match_id' => 'linked']);
        $unlinkedMatch = $this->match($competition, $sameNameButUnlinked, $opponent, ['provider_match_id' => 'name-only']);

        $this->get(route('teams.fixtures', $team))
            ->assertOk()
            ->assertSee(route('matches.show', $linkedMatch), false)
            ->assertDontSee(route('matches.show', $unlinkedMatch), false);
    }

    public function test_home_and_away_matches_are_listed_but_other_matches_are_excluded(): void
    {
        $team = $this->team();
        $competition = $this->competition();
        $linked = $this->footballTeam('Bağlı', $team);
        $firstOpponent = $this->footballTeam('İlk Rakip');
        $secondOpponent = $this->footballTeam('İkinci Rakip');
        $otherHome = $this->footballTeam('Başka Ev');
        $otherAway = $this->footballTeam('Başka Deplasman');

        $homeMatch = $this->match($competition, $linked, $firstOpponent, ['provider_match_id' => 'home-match']);
        $awayMatch = $this->match($competition, $secondOpponent, $linked, ['provider_match_id' => 'away-match', 'kickoff_at' => '2026-09-11 18:00:00']);
        $otherMatch = $this->match($competition, $otherHome, $otherAway, ['provider_match_id' => 'other-match']);

        $this->get(route('teams.fixtures', $team))
            ->assertOk()
            ->assertSee(route('matches.show', $homeMatch), false)
            ->assertSee(route('matches.show', $awayMatch), false)
            ->assertDontSee(route('matches.show', $otherMatch), false);
    }

    public function test_competition_chips_are_dynamic_active_and_filter_matches(): void
    {
        $team = $this->team();
        $league = $this->competition(['provider_league_id' => 'league', 'name' => 'Lig', 'display_name' => 'Süper Lig', 'slug' => 'lig']);
        $europe = $this->competition(['provider_league_id' => 'europe', 'name' => 'Avrupa', 'display_name' => 'Avrupa Ligi', 'slug' => 'avrupa']);
        $inactive = $this->competition(['provider_league_id' => 'inactive', 'name' => 'Pasif Kupa', 'slug' => 'pasif', 'is_active' => false]);
        $linked = $this->footballTeam('Bağlı', $team);
        $leagueOpponent = $this->footballTeam('Lig Rakibi');
        $europeOpponent = $this->footballTeam('Avrupa Rakibi');
        $inactiveOpponent = $this->footballTeam('Pasif Rakip');
        $this->match($league, $linked, $leagueOpponent, ['provider_match_id' => 'league-match']);
        $this->match($europe, $linked, $europeOpponent, ['provider_match_id' => 'europe-match']);
        $this->match($inactive, $linked, $inactiveOpponent, ['provider_match_id' => 'inactive-match']);

        $this->get(route('teams.fixtures', ['team' => $team, 'competition' => $league->id]))
            ->assertOk()
            ->assertSee('Süper Lig')
            ->assertSee('Avrupa Ligi')
            ->assertDontSee('Pasif Kupa')
            ->assertSee('Lig Rakibi')
            ->assertDontSee('Avrupa Rakibi')
            ->assertDontSee('Pasif Rakip');
    }

    public function test_upcoming_and_results_apply_time_and_status_rules(): void
    {
        $team = $this->team();
        $competition = $this->competition();
        $linked = $this->footballTeam('Bağlı', $team);
        $futureOpponent = $this->footballTeam('Gelecek Rakip');
        $pastScheduledOpponent = $this->footballTeam('Geçmiş Planlı');
        $finishedOpponent = $this->footballTeam('Biten Rakip');
        $cancelledOpponent = $this->footballTeam('İptal Rakip');

        $this->match($competition, $linked, $futureOpponent, ['provider_match_id' => 'future']);
        $this->match($competition, $linked, $pastScheduledOpponent, ['provider_match_id' => 'past-scheduled', 'kickoff_at' => '2026-09-08 18:00:00']);
        $this->match($competition, $linked, $finishedOpponent, ['provider_match_id' => 'finished', 'kickoff_at' => '2026-09-08 17:00:00', 'status' => 'finished', 'status_display' => 'Bitti', 'home_score' => 2, 'away_score' => 1]);
        $this->match($competition, $linked, $cancelledOpponent, ['provider_match_id' => 'cancelled', 'kickoff_at' => '2026-09-08 16:00:00', 'status' => 'cancelled']);

        $this->get(route('teams.fixtures', $team))
            ->assertOk()
            ->assertSee('Gelecek Rakip')
            ->assertDontSee('Geçmiş Planlı')
            ->assertDontSee('Biten Rakip')
            ->assertDontSee('İptal Rakip');

        $this->get(route('teams.fixtures', ['team' => $team, 'view' => 'results']))
            ->assertOk()
            ->assertSee('Biten Rakip')
            ->assertSee('2 - 1')
            ->assertDontSee('Gelecek Rakip')
            ->assertDontSee('İptal Rakip');
    }

    public function test_fixture_kickoff_is_rendered_only_in_istanbul_time_and_cards_link_to_match_details(): void
    {
        $team = $this->team();
        $competition = $this->competition();
        $linked = $this->footballTeam('Bağlı', $team);
        $opponent = $this->footballTeam('Rakip');
        $match = $this->match($competition, $linked, $opponent, ['kickoff_at' => '2026-09-09 19:00:00']);

        $this->get(route('teams.fixtures', $team))
            ->assertOk()
            ->assertSee('22:00')
            ->assertDontSee('19:00')
            ->assertSee(route('matches.show', $match), false);
    }

    public function test_upcoming_matches_are_oldest_first_and_limited_to_fifteen(): void
    {
        $team = $this->team();
        $competition = $this->competition();
        $linked = $this->footballTeam('Bağlı', $team);

        for ($day = 10; $day <= 25; $day++) {
            $opponent = $this->footballTeam("Rakip {$day}");
            $this->match($competition, $linked, $opponent, [
                'provider_match_id' => "match-day-{$day}",
                'kickoff_at' => "2026-09-{$day} 18:00:00",
            ]);
        }

        $response = $this->get(route('teams.fixtures', $team))->assertOk()->assertDontSee('Rakip 25');
        $html = $response->getContent();

        $this->assertLessThan(strpos($html, 'Rakip 24'), strpos($html, 'Rakip 10'));
    }

    public function test_results_are_ordered_from_newest_to_oldest(): void
    {
        $team = $this->team();
        $competition = $this->competition();
        $linked = $this->footballTeam('Bağlı', $team);
        $older = $this->footballTeam('Eski Sonuç');
        $newer = $this->footballTeam('Yeni Sonuç');
        $this->match($competition, $linked, $older, ['provider_match_id' => 'older', 'kickoff_at' => '2026-09-01 18:00:00', 'status' => 'finished']);
        $this->match($competition, $linked, $newer, ['provider_match_id' => 'newer', 'kickoff_at' => '2026-09-08 18:00:00', 'status' => 'finished']);

        $html = $this->get(route('teams.fixtures', ['team' => $team, 'view' => 'results']))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Eski Sonuç'), strpos($html, 'Yeni Sonuç'));
    }

    public function test_fixture_queries_eager_load_card_relations_and_make_no_upstream_requests(): void
    {
        $team = $this->team();
        $competition = $this->competition();
        $linked = $this->footballTeam('Bağlı', $team);
        $this->match($competition, $linked, $this->footballTeam('Rakip'));
        Http::fake();
        Model::preventLazyLoading();

        try {
            $this->get(route('teams.fixtures', $team))->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }

        Http::assertNothingSent();
    }

    public function test_player_tab_lists_only_active_players_for_the_social_team_with_profile_links(): void
    {
        $team = $this->team();
        $otherTeam = $this->team(['name' => 'Başka Takım', 'slug' => 'baska-takim']);
        $player = Player::factory()->create(['name' => 'Fernando Muslera', 'slug' => 'fernando-muslera', 'current_team_id' => $team->id, 'position' => 'Kaleci', 'shirt_number' => 1]);
        $other = Player::factory()->create(['name' => 'Başka Oyuncu', 'slug' => 'baska-oyuncu', 'current_team_id' => $otherTeam->id]);
        $inactive = Player::factory()->inactive()->create(['name' => 'Pasif Oyuncu', 'slug' => 'pasif-oyuncu', 'current_team_id' => $team->id]);

        $this->get(route('teams.players', $team))
            ->assertOk()
            ->assertSee($player->name)
            ->assertSee('Kaleci')
            ->assertSee('1')
            ->assertSee(route('players.show', $player), false)
            ->assertDontSee($other->name)
            ->assertDontSee($inactive->name);
    }

    public function test_player_search_supports_turkish_characters_and_has_a_no_results_state(): void
    {
        $team = $this->team();
        Player::factory()->create(['name' => 'Çağlar Söyüncü', 'slug' => 'caglar-soyuncu', 'current_team_id' => $team->id]);
        Player::factory()->create(['name' => 'Kerem Aktürkoğlu', 'slug' => 'kerem-akturkoglu', 'current_team_id' => $team->id]);

        $this->get(route('teams.players', ['team' => $team, 'q' => 'Çağlar']))
            ->assertOk()
            ->assertSee('Çağlar Söyüncü')
            ->assertDontSee('Kerem Aktürkoğlu');

        $this->get(route('teams.players', ['team' => $team, 'q' => 'Şenol']))
            ->assertOk()
            ->assertSee('Aramanızla eşleşen oyuncu bulunamadı.');
    }

    public function test_player_list_uses_existing_manual_order(): void
    {
        $team = $this->team();
        Player::factory()->create(['name' => 'İkinci Oyuncu', 'slug' => 'ikinci', 'current_team_id' => $team->id, 'sort_order' => 20]);
        Player::factory()->create(['name' => 'Birinci Oyuncu', 'slug' => 'birinci', 'current_team_id' => $team->id, 'sort_order' => 10]);

        $html = $this->get(route('teams.players', $team))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'İkinci Oyuncu'), strpos($html, 'Birinci Oyuncu'));
    }

    public function test_playerless_team_has_the_requested_empty_state(): void
    {
        $this->get(route('teams.players', $this->team()))
            ->assertOk()
            ->assertSee('Bu takımda henüz oyuncu bulunmuyor.');
    }

    public function test_social_team_without_football_link_has_a_clean_empty_state(): void
    {
        $this->get(route('teams.fixtures', $this->team()))
            ->assertOk()
            ->assertSee('Bu takım için fikstür verisi henüz eşleştirilmedi.');
    }

    public function test_unlinked_football_team_keeps_its_fallback_page_while_linked_team_redirects(): void
    {
        $team = $this->team();
        $competition = $this->competition();
        $linked = $this->footballTeam('Bağlı', $team);
        $unlinked = $this->footballTeam('Sporting CP');
        $this->match($competition, $linked, $unlinked);

        $this->get(route('football-teams.show', $linked))->assertRedirect(route('teams.show', $team));
        $this->get(route('football-teams.show', $unlinked))->assertOk()->assertSee('Sporting CP');
    }

    public function test_inactive_social_team_tabs_remain_private(): void
    {
        $team = $this->team(['status' => TeamStatus::Inactive]);

        $this->get(route('teams.show', $team))->assertNotFound();
        $this->get(route('teams.fixtures', $team))->assertNotFound();
        $this->get(route('teams.players', $team))->assertNotFound();
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
        ], $attributes));
    }

    private function footballTeam(string $name, ?Team $team = null): FootballTeam
    {
        static $sequence = 0;
        $sequence++;

        return FootballTeam::create([
            'provider' => 'live-football-api',
            'provider_team_id' => 'team-'.$sequence,
            'provider_name' => $name,
            'display_name' => $name,
            'team_id' => $team?->id,
            'is_active' => true,
        ]);
    }

    private function match(FootballCompetition $competition, FootballTeam $home, FootballTeam $away, array $attributes = []): FootballMatch
    {
        static $sequence = 0;
        $sequence++;

        return FootballMatch::create(array_merge([
            'competition_id' => $competition->id,
            'home_football_team_id' => $home->id,
            'away_football_team_id' => $away->id,
            'provider' => 'live-football-api',
            'provider_match_id' => 'match-'.$sequence,
            'kickoff_at' => '2026-09-10 18:00:00',
            'status' => 'scheduled',
            'status_display' => 'Başlamadı',
            'is_live' => false,
        ], $attributes));
    }
}
