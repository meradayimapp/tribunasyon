<?php

namespace Tests\Feature\Football;

use App\Enums\TeamStatus;
use App\Enums\UserRole;
use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\Football\FootballTeamSocialMapper;
use App\Services\Football\LiveFootballApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FootballSocialTeamLinkCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-09 12:00:00');
        Http::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_all_eighteen_explicit_provider_team_ids_map_to_production_slugs(): void
    {
        $this->assertSame([
            'esa748l653sss1wurz5ps3228' => 'galatasaray',
            '8lroq0cbhdxj8124qtxwrhvmm' => 'fenerbahce',
            '2ez9cvam9lp9jyhng3eh3znb4' => 'besiktas',
            '2yab38jdfl0gk2tei1mq40o06' => 'trabzonspor',
            '47njg6cmlx5q3fvdsupd2n6qu' => 'basaksehir',
            'cw4lbdzlqqdvbkdkz00c9ye49' => 'konyaspor',
            '1lbrlj3uu8wi2h9j79snuoae4' => 'rizespor',
            '2agzb2h4ppg7lfz9hn7eg1rqo' => 'gaziantep-fk',
            '84fpe0iynjdghwysyo5tizdkk' => 'alanyaspor',
            'embqktr41hfzczc8uav1scmcn' => 'genclerbirligi',
            '4idg23egrrvtrbgrg7p5x7bwf' => 'kasimpasa',
            'dpsnqu7pd2b0shfzjyn5j1znf' => 'samsunspor',
            'cjbaf8s09qoa1n11r33gc560x' => 'goztepe',
            'bmgtxgipsznlb1j20zwjti3xh' => 'eyupspor',
            'b703zecenioz21dnj3p63v3f7' => 'kocaelispor',
            '2154uhyeun0lm781iiiijqhwo' => 'amed',
            'ea2gyhkv6vwmxbxevdb4u3796' => 'erzurumspor',
            'eg0cqg1u8zz85ma9nzk0cijv' => 'corum-fk',
        ], FootballTeamSocialMapper::MAPPINGS);
    }

    public function test_command_links_representative_teams_and_is_idempotent_without_api_calls(): void
    {
        $representatives = [
            'esa748l653sss1wurz5ps3228',
            'b703zecenioz21dnj3p63v3f7',
            '2154uhyeun0lm781iiiijqhwo',
            'eg0cqg1u8zz85ma9nzk0cijv',
            '84fpe0iynjdghwysyo5tizdkk',
        ];

        foreach ($representatives as $index => $providerTeamId) {
            $this->socialTeam($providerTeamId, $index);
        }
        foreach (array_keys(FootballTeamSocialMapper::MAPPINGS) as $index => $providerTeamId) {
            $this->footballTeam($providerTeamId, "Unrelated provider name {$index}");
        }

        $this->artisan('football:link-social-teams')
            ->expectsOutput('Successfully linked: 5')
            ->expectsOutput('Linked: 5')
            ->expectsOutput('Already linked: 0')
            ->expectsOutput('Missing social team: 13')
            ->expectsOutput('Missing football team: 0')
            ->assertExitCode(0);

        foreach ($representatives as $providerTeamId) {
            $slug = FootballTeamSocialMapper::MAPPINGS[$providerTeamId];
            $socialTeam = Team::query()->where('slug', $slug)->firstOrFail();
            $this->assertDatabaseHas('football_teams', [
                'provider_team_id' => $providerTeamId,
                'team_id' => $socialTeam->id,
            ]);
        }

        $this->artisan('football:link-social-teams')
            ->expectsOutput('Successfully linked: 5')
            ->expectsOutput('Linked: 0')
            ->expectsOutput('Already linked: 5')
            ->assertExitCode(0);

        $this->assertDatabaseCount('football_teams', 18);
        Http::assertNothingSent();
    }

    public function test_mapping_uses_only_explicit_provider_id_and_social_slug(): void
    {
        $socialTeam = $this->socialTeam('esa748l653sss1wurz5ps3228');
        $unlisted = $this->footballTeam('not-an-explicit-id', 'Galatasaray');

        app(FootballTeamSocialMapper::class)->linkExistingTeams();

        $this->assertNull($unlisted->fresh()->team_id);
        $this->assertSame(
            $socialTeam->id,
            app(FootballTeamSocialMapper::class)->socialTeamId('esa748l653sss1wurz5ps3228'),
        );
        $this->assertStringNotContainsString(
            'provider_name',
            file_get_contents(app_path('Services/Football/FootballTeamSocialMapper.php')),
        );
        $this->assertStringNotContainsString(
            "where('name'",
            file_get_contents(app_path('Services/Football/FootballTeamSocialMapper.php')),
        );
        Http::assertNothingSent();
    }

    public function test_missing_social_and_missing_football_records_are_reported_safely(): void
    {
        $this->footballTeam('esa748l653sss1wurz5ps3228', 'Anything');

        $this->artisan('football:link-social-teams')
            ->expectsOutput('Missing social team: 18')
            ->expectsOutput('Missing football team: 0')
            ->assertExitCode(0);

        FootballTeam::query()->delete();
        $this->socialTeam('esa748l653sss1wurz5ps3228');

        $this->artisan('football:link-social-teams')
            ->expectsOutput('Missing social team: 17')
            ->expectsOutput('Missing football team: 1')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_wrong_existing_link_is_corrected_and_correct_link_is_preserved(): void
    {
        $galatasaray = $this->socialTeam('esa748l653sss1wurz5ps3228');
        $wrong = Team::create([
            'name' => 'Yanlış Takım',
            'slug' => 'yanlis-takim',
            'short_name' => 'YT',
            'primary_color' => '#111111',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
        ]);
        $footballTeam = $this->footballTeam('esa748l653sss1wurz5ps3228', 'Different Provider Name', $wrong);

        $this->artisan('football:link-social-teams')
            ->expectsOutput('Corrected: 1')
            ->assertExitCode(0);
        $this->assertSame($galatasaray->id, $footballTeam->fresh()->team_id);

        $this->artisan('football:link-social-teams')
            ->expectsOutput('Already linked: 1')
            ->expectsOutput('Corrected: 0')
            ->assertExitCode(0);
    }

    public function test_representative_linked_profiles_render_home_and_away_fixtures(): void
    {
        $providerIds = [
            '84fpe0iynjdghwysyo5tizdkk',
            '2154uhyeun0lm781iiiijqhwo',
            '47njg6cmlx5q3fvdsupd2n6qu',
            '2ez9cvam9lp9jyhng3eh3znb4',
            'eg0cqg1u8zz85ma9nzk0cijv',
            'ea2gyhkv6vwmxbxevdb4u3796',
            'esa748l653sss1wurz5ps3228',
            'b703zecenioz21dnj3p63v3f7',
            'dpsnqu7pd2b0shfzjyn5j1znf',
            'cjbaf8s09qoa1n11r33gc560x',
        ];
        $competition = FootballCompetition::create([
            'provider' => LiveFootballApiService::PROVIDER,
            'provider_league_id' => 'super-lig-2026',
            'name' => 'Trendyol Süper Lig',
            'slug' => 'super-lig-2026',
            'is_active' => true,
        ]);
        $foreign = $this->footballTeam('foreign-team', 'Foreign FC');

        foreach ($providerIds as $index => $providerTeamId) {
            $socialTeam = $this->socialTeam($providerTeamId, $index);
            $footballTeam = $this->footballTeam($providerTeamId, "Provider {$index}");
            $homeMatch = $this->match($competition, $footballTeam, $foreign, "home-{$index}", '2026-09-'.str_pad((string) (10 + $index), 2, '0', STR_PAD_LEFT).' 17:00:00');
            $awayMatch = $this->match($competition, $foreign, $footballTeam, "away-{$index}", '2026-10-'.str_pad((string) (10 + $index), 2, '0', STR_PAD_LEFT).' 17:00:00');

            app(FootballTeamSocialMapper::class)->linkExistingTeams();

            $this->get(route('teams.fixtures', $socialTeam))
                ->assertOk()
                ->assertSee('Trendyol Süper Lig')
                ->assertSee(route('matches.show', $homeMatch), false)
                ->assertSee(route('matches.show', $awayMatch), false);
        }

        Http::assertNothingSent();
    }

    public function test_linked_team_redirects_to_social_profile_and_foreign_team_keeps_fallback(): void
    {
        $socialTeam = $this->socialTeam('esa748l653sss1wurz5ps3228');
        $linked = $this->footballTeam('esa748l653sss1wurz5ps3228', 'Provider Galatasaray');
        $foreign = $this->footballTeam('foreign-club', 'Sporting CP');
        app(FootballTeamSocialMapper::class)->linkExistingTeams();

        $this->get(route('football-teams.show', $linked))
            ->assertRedirect(route('teams.show', $socialTeam));
        $this->get(route('football-teams.show', $foreign))
            ->assertOk()
            ->assertSee('Sporting CP');
    }

    public function test_admin_team_list_displays_football_api_link_status_without_n_plus_one(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $linkedTeam = $this->socialTeam('esa748l653sss1wurz5ps3228');
        $this->footballTeam('esa748l653sss1wurz5ps3228', 'Provider Galatasaray', $linkedTeam);
        Team::create([
            'name' => 'Bağsız Takım',
            'slug' => 'bagsiz-takim',
            'short_name' => 'BT',
            'primary_color' => '#111111',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.teams.index'))
            ->assertOk()
            ->assertSee('Futbol API')
            ->assertSee('Bağlı')
            ->assertSee('Bağlı değil')
            ->assertSee('esa748l653sss1wurz5ps3228');
    }

    private function socialTeam(string $providerTeamId, int $index = 0): Team
    {
        $slug = FootballTeamSocialMapper::MAPPINGS[$providerTeamId];

        return Team::create([
            'name' => "Social Team {$index}",
            'slug' => $slug,
            'short_name' => "ST{$index}",
            'primary_color' => '#111111',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
        ]);
    }

    private function footballTeam(string $providerTeamId, string $providerName, ?Team $team = null): FootballTeam
    {
        return FootballTeam::create([
            'provider' => LiveFootballApiService::PROVIDER,
            'provider_team_id' => $providerTeamId,
            'provider_name' => $providerName,
            'is_active' => true,
            'team_id' => $team?->id,
        ]);
    }

    private function match(
        FootballCompetition $competition,
        FootballTeam $home,
        FootballTeam $away,
        string $providerMatchId,
        string $kickoffAt,
    ): FootballMatch {
        return FootballMatch::create([
            'competition_id' => $competition->id,
            'home_football_team_id' => $home->id,
            'away_football_team_id' => $away->id,
            'provider' => LiveFootballApiService::PROVIDER,
            'provider_match_id' => $providerMatchId,
            'kickoff_at' => $kickoffAt,
            'status' => 'scheduled',
            'status_display' => 'Başlamadı',
            'is_live' => false,
        ]);
    }
}
