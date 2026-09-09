<?php

namespace Tests\Feature\Football;

use App\Enums\TeamStatus;
use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_detail_is_public_server_rendered_and_uses_team_destinations(): void
    {
        [$match, $home, $away] = $this->match();
        $community = Team::create([
            'name' => 'Bağlı Topluluk', 'slug' => 'bagli', 'short_name' => 'BT',
            'primary_color' => '#111111', 'secondary_color' => '#ffffff', 'status' => TeamStatus::Active,
        ]);
        $home->update(['team_id' => $community->id]);

        $this->get(route('matches.show', $match))
            ->assertOk()
            ->assertSee('Bağlı Topluluk')
            ->assertSee('Rakip')
            ->assertSee('20:00')
            ->assertSee('Başlamadı')
            ->assertSee('Canlı Maç Sohbeti')
            ->assertSee('Giriş yap')
            ->assertSee(route('teams.show', $community), false)
            ->assertSee(route('football-teams.show', $away), false);
    }

    public function test_linked_fallback_route_redirects_and_unlinked_team_page_lists_matches(): void
    {
        [$match, $home, $away] = $this->match();
        $community = Team::create([
            'name' => 'Topluluk', 'slug' => 'topluluk', 'short_name' => 'TPL',
            'primary_color' => '#111111', 'secondary_color' => '#ffffff', 'status' => TeamStatus::Active,
        ]);
        $home->update(['team_id' => $community->id]);

        $this->get(route('football-teams.show', $home))->assertRedirect(route('teams.show', $community));
        $this->get(route('football-teams.show', $away))
            ->assertOk()
            ->assertSee('Rakip')
            ->assertSee('Test Ligi')
            ->assertSee(route('matches.show', $match), false);
    }

    public function test_state_endpoint_returns_database_only_live_and_finished_states(): void
    {
        [$match] = $this->match([
            'status' => 'live', 'status_display' => 'CANLI', 'is_live' => true,
            'home_score' => 1, 'away_score' => 0, 'live_minute' => 37,
        ]);
        Http::fake();

        $this->getJson(route('matches.state', $match))
            ->assertOk()
            ->assertExactJson([
                'status' => 'live', 'is_live' => true, 'is_finished' => false,
                'score' => ['home' => 1, 'away' => 0], 'minute' => 37,
                'status_display' => 'CANLI', 'updated_at' => null,
            ]);
        Http::assertNothingSent();

        $match->update(['status' => 'finished', 'status_display' => 'Bitti', 'is_live' => false, 'home_score' => 2, 'away_score' => 1]);
        $this->getJson(route('matches.state', $match))
            ->assertOk()
            ->assertJsonPath('is_live', false)
            ->assertJsonPath('is_finished', true)
            ->assertJsonPath('score.home', 2)
            ->assertJsonPath('status_display', 'Bitti');
        Http::assertNothingSent();
    }

    public function test_match_pages_eager_load_their_required_relations(): void
    {
        [$match, , $away] = $this->match();
        Model::preventLazyLoading();

        try {
            $this->get(route('matches.index'))->assertOk();
            $this->get(route('matches.show', $match))->assertOk();
            $this->get(route('football-teams.show', $away))->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    private function match(array $overrides = []): array
    {
        $competition = FootballCompetition::create([
            'provider' => 'live-football-api', 'provider_league_id' => 'league-1',
            'name' => 'Test Ligi', 'slug' => 'test-ligi', 'is_active' => true,
        ]);
        $home = FootballTeam::create([
            'provider' => 'live-football-api', 'provider_team_id' => 'home-1',
            'provider_name' => 'Ev Sahibi', 'is_active' => true,
        ]);
        $away = FootballTeam::create([
            'provider' => 'live-football-api', 'provider_team_id' => 'away-1',
            'provider_name' => 'Rakip', 'is_active' => true,
        ]);
        $match = FootballMatch::create(array_merge([
            'competition_id' => $competition->id,
            'home_football_team_id' => $home->id,
            'away_football_team_id' => $away->id,
            'provider' => 'live-football-api', 'provider_match_id' => 'match-1',
            'kickoff_at' => '2026-09-09 17:00:00', 'status' => 'scheduled',
            'status_display' => 'Başlamadı', 'is_live' => false,
        ], $overrides));

        return [$match, $home, $away];
    }
}
