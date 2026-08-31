<?php

namespace Tests\Feature\Admin;

use App\Enums\TeamStatus;
use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TeamLogoSelectionTest extends TestCase
{
    use RefreshDatabase;

    private string $logoPath = 'teams/logos/test-admin-dropdown.png';

    private string $logoFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logoFile = public_path('storage/'.$this->logoPath);
        File::ensureDirectoryExists(dirname($this->logoFile));
        File::put($this->logoFile, 'test-logo');
    }

    protected function tearDown(): void
    {
        File::delete($this->logoFile);

        parent::tearDown();
    }

    public function test_admin_edit_form_lists_logo_files_from_the_storage_directory(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $team = $this->team();

        $this->actingAs($admin)
            ->get(route('admin.teams.edit', $team))
            ->assertOk()
            ->assertSee('test-admin-dropdown.png')
            ->assertSee($this->logoPath);
    }

    public function test_admin_can_select_a_catalog_logo_for_a_team(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $team = $this->team();

        $this->actingAs($admin)
            ->put(route('admin.teams.update', $team), $this->teamPayload($team, $this->logoPath))
            ->assertRedirect(route('admin.teams.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'logo' => $this->logoPath]);
    }

    public function test_existing_static_logo_is_matched_to_catalog_by_filename(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $team = $this->team(['logo' => 'images/teams/logos/test-admin-dropdown.png']);

        $this->actingAs($admin)
            ->get(route('admin.teams.edit', $team))
            ->assertOk()
            ->assertSee('value="'.$this->logoPath.'" selected', false);
    }

    public function test_logo_outside_the_catalog_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $team = $this->team();

        $this->actingAs($admin)
            ->from(route('admin.teams.edit', $team))
            ->put(route('admin.teams.update', $team), $this->teamPayload($team, '../organizations-images/badge.png'))
            ->assertRedirect(route('admin.teams.edit', $team))
            ->assertSessionHasErrors('logo');

        $this->assertNull($team->fresh()->logo);
    }

    public function test_admin_can_remove_the_logo_association_without_deleting_the_asset(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $team = $this->team(['logo' => $this->logoPath]);

        $this->actingAs($admin)
            ->put(route('admin.teams.update', $team), $this->teamPayload($team, ''))
            ->assertRedirect(route('admin.teams.index'));

        $this->assertNull($team->fresh()->logo);
        $this->assertFileExists($this->logoFile);
    }

    public function test_normal_member_cannot_change_a_team_logo(): void
    {
        $member = User::factory()->create();
        $team = $this->team();

        $this->actingAs($member)
            ->put(route('admin.teams.update', $team), $this->teamPayload($team, $this->logoPath))
            ->assertForbidden();

        $this->assertNull($team->fresh()->logo);
    }

    private function team(array $attributes = []): Team
    {
        return Team::create(array_merge([
            'name' => 'Logo Test Takımı',
            'slug' => 'logo-test-takimi',
            'short_name' => 'LTT',
            'primary_color' => '#172554',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
        ], $attributes));
    }

    private function teamPayload(Team $team, ?string $logo): array
    {
        return [
            'name' => $team->name,
            'slug' => $team->slug,
            'short_name' => $team->short_name,
            'primary_color' => $team->primary_color,
            'secondary_color' => $team->secondary_color,
            'status' => TeamStatus::Active->value,
            'logo' => $logo,
        ];
    }
}
