<?php

namespace Tests\Feature\Auth;

use App\Enums\TeamStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_lists_every_active_team_and_hides_inactive_and_deleted_teams(): void
    {
        $activeTeams = collect(range(1, 6))->map(fn (int $number) => $this->team([
            'name' => "Aktif Takım {$number}",
            'slug' => "aktif-takim-{$number}",
        ]));
        $inactiveTeam = $this->team(['name' => 'Pasif Takım', 'slug' => 'pasif-takim', 'status' => TeamStatus::Inactive]);
        $deletedTeam = $this->team(['name' => 'Silinmiş Takım', 'slug' => 'silinmis-takim']);
        $deletedTeam->delete();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('onboarding.teams.edit'))
            ->assertOk()
            ->assertViewHas('teams', fn ($teams) => $teams->count() === 6
                && $activeTeams->every(fn (Team $team) => $teams->contains($team)));

        $activeTeams->each(fn (Team $team) => $response->assertSee($team->name));
        $response->assertDontSee($inactiveTeam->name)->assertDontSee($deletedTeam->name);
    }

    public function test_onboarding_saves_favorite_and_followed_teams_using_existing_relations(): void
    {
        $user = User::factory()->create();
        [$favorite, $followed, $notFollowed] = [$this->team(), $this->team(), $this->team()];

        $this->actingAs($user)->put(route('onboarding.teams.update'), [
            'favorite_team_id' => $favorite->id,
            'team_ids' => [$followed->id],
        ])->assertRedirect(route('home'));

        $this->assertSame($favorite->id, $user->fresh()->favorite_team_id);
        $this->assertDatabaseHas('team_follows', ['user_id' => $user->id, 'team_id' => $favorite->id]);
        $this->assertDatabaseHas('team_follows', ['user_id' => $user->id, 'team_id' => $followed->id]);
        $this->assertDatabaseMissing('team_follows', ['user_id' => $user->id, 'team_id' => $notFollowed->id]);
        $this->assertSame(2, $user->followedTeams()->count());
    }

    public function test_onboarding_rejects_inactive_teams(): void
    {
        $user = User::factory()->create();
        $inactiveTeam = $this->team(['status' => TeamStatus::Inactive]);

        $this->actingAs($user)->from(route('onboarding.teams.edit'))->put(route('onboarding.teams.update'), [
            'favorite_team_id' => $inactiveTeam->id,
            'team_ids' => [$inactiveTeam->id],
        ])->assertRedirect(route('onboarding.teams.edit'))->assertSessionHasErrors(['favorite_team_id', 'team_ids.0']);

        $this->assertNull($user->fresh()->favorite_team_id);
        $this->assertFalse($user->followedTeams()->exists());
    }

    public function test_onboarding_can_be_skipped_without_selecting_a_team(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.teams.skip'))->assertRedirect(route('home'));

        $this->assertNull($user->fresh()->favorite_team_id);
        $this->assertFalse($user->followedTeams()->exists());
    }

    private function team(array $attributes = []): Team
    {
        static $sequence = 0;
        $sequence++;

        return Team::create(array_merge([
            'name' => "Takım {$sequence}",
            'slug' => "takim-{$sequence}",
            'short_name' => "T{$sequence}",
            'primary_color' => '#111111',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
        ], $attributes));
    }
}
