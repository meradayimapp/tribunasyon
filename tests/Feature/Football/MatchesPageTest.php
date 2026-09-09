<?php

namespace Tests\Feature\Football;

use App\Enums\TeamStatus;
use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MatchesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_displays_database_matches_in_istanbul_time_and_resolves_team_names(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:00', 'Europe/Istanbul'));

        try {
            $communityTeam = Team::create([
                'name' => 'Fenerbahçe Topluluğu',
                'slug' => 'fenerbahce',
                'short_name' => 'FB',
                'primary_color' => '#0b2d72',
                'secondary_color' => '#f3d21b',
                'status' => TeamStatus::Active,
            ]);
            $competition = FootballCompetition::create([
                'provider' => 'live-football-api',
                'provider_league_id' => 'league-1',
                'name' => 'Provider League',
                'display_name' => 'Süper Lig',
                'slug' => 'super-lig',
                'timezone' => 'UTC',
                'is_active' => true,
                'sort_order' => 10,
            ]);
            $home = FootballTeam::create([
                'provider' => 'live-football-api',
                'provider_team_id' => 'home-1',
                'provider_name' => 'Provider Fenerbahçe',
                'display_name' => 'Display Fenerbahçe',
                'team_id' => $communityTeam->id,
                'is_active' => true,
            ]);
            $away = FootballTeam::create([
                'provider' => 'live-football-api',
                'provider_team_id' => 'away-1',
                'provider_name' => 'Provider Arsenal',
                'display_name' => 'Arsenal',
                'is_active' => true,
            ]);
            $match = FootballMatch::create([
                'competition_id' => $competition->id,
                'home_football_team_id' => $home->id,
                'away_football_team_id' => $away->id,
                'provider' => 'live-football-api',
                'provider_match_id' => 'match-1',
                'kickoff_at' => Carbon::parse('2026-09-08 17:00:00', 'UTC'),
                'status' => 'live',
                'status_display' => "73'",
                'is_live' => true,
                'home_score' => 2,
                'away_score' => 1,
            ]);

            $match = FootballMatch::query()->firstOrFail();
            $response = $this->get('/maclar')
                ->assertOk()
                ->assertSee('Fenerbahçe Topluluğu')
                ->assertDontSee('Display Fenerbahçe')
                ->assertSee('Arsenal')
                ->assertSee('Süper Lig')
                ->assertDontSee('>20:00<', false)
                ->assertSee('<strong>2 <span>–</span> 1</strong>', false)
                ->assertSee('73′')
                ->assertSee('match-card-live-label', false)
                ->assertSee(route('matches.show', $match), false)
                ->assertSee(route('teams.show', $communityTeam), false)
                ->assertSee(route('football-teams.show', $away), false)
                ->assertDontSee('17:00');

            $this->assertSame(0, substr_count($response->getContent(), '>20:00<'));
            $this->assertSame(1, substr_count($response->getContent(), 'match-card-live-label'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_resolved_name_falls_back_to_display_then_provider_name(): void
    {
        $display = new FootballTeam(['provider_name' => 'Provider', 'display_name' => 'Display']);
        $provider = new FootballTeam(['provider_name' => 'Provider']);

        $this->assertSame('Display', $display->resolved_name);
        $this->assertSame('Provider', $provider->resolved_name);
    }

    public function test_scheduled_provider_utc_status_time_is_not_rendered_as_a_second_kickoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:00', 'Europe/Istanbul'));

        try {
            $competition = FootballCompetition::create([
                'provider' => 'live-football-api', 'provider_league_id' => 'league-time',
                'name' => 'Lig', 'slug' => 'lig-time', 'is_active' => true,
            ]);
            $home = FootballTeam::create([
                'provider' => 'live-football-api', 'provider_team_id' => 'time-home',
                'provider_name' => 'Ev', 'is_active' => true,
            ]);
            $away = FootballTeam::create([
                'provider' => 'live-football-api', 'provider_team_id' => 'time-away',
                'provider_name' => 'Deplasman', 'is_active' => true,
            ]);
            $match = FootballMatch::create([
                'competition_id' => $competition->id,
                'home_football_team_id' => $home->id,
                'away_football_team_id' => $away->id,
                'provider' => 'live-football-api', 'provider_match_id' => 'time-match',
                'kickoff_at' => '2026-09-08 19:00:00',
                'status' => 'scheduled', 'status_display' => '19:00', 'is_live' => false,
            ]);

            $response = $this->get(route('matches.index'))
                ->assertOk()
                ->assertSee('22:00')
                ->assertSee('Başlamadı')
                ->assertDontSee('19:00');
            $this->assertSame(1, substr_count($response->getContent(), '>22:00<'));

            $match->update([
                'status' => 'finished',
                'status_display' => 'Bitti',
                'home_score' => 3,
                'away_score' => 2,
            ]);

            $this->get(route('matches.index'))
                ->assertOk()
                ->assertSee('<strong>3 <span>–</span> 2</strong>', false)
                ->assertSee('Bitti')
                ->assertDontSee('>22:00<', false);
        } finally {
            Carbon::setTestNow();
        }
    }
}
