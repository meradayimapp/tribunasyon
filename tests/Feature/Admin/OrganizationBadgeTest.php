<?php

namespace Tests\Feature\Admin;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\TeamStatus;
use App\Enums\UserRole;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OrganizationBadgeTest extends TestCase
{
    use RefreshDatabase;

    private string $badgePath = 'organizations-images/uefa-sampiyonlar-ligi-test.svg';

    private string $badgeFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->badgeFile = public_path('storage/'.$this->badgePath);
        File::ensureDirectoryExists(dirname($this->badgeFile));
        File::put($this->badgeFile, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8"/></svg>');
    }

    protected function tearDown(): void
    {
        File::delete($this->badgeFile);

        parent::tearDown();
    }

    public function test_admin_can_select_an_available_organization_badge(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $team = $this->team();

        $this->actingAs($admin)
            ->put(route('admin.teams.update', $team), $this->teamPayload($team, $this->badgePath))
            ->assertRedirect(route('admin.teams.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_selected_organization_badge_is_stored_in_database(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $team = $this->team();

        $this->actingAs($admin)->put(route('admin.teams.update', $team), $this->teamPayload($team, $this->badgePath));

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'organization_badge' => $this->badgePath]);
    }

    public function test_admin_can_remove_organization_badge_without_deleting_file(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $team = $this->team(['organization_badge' => $this->badgePath]);

        $this->actingAs($admin)
            ->put(route('admin.teams.update', $team), $this->teamPayload($team, ''))
            ->assertRedirect(route('admin.teams.index'));

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'organization_badge' => null]);
        $this->assertFileExists($this->badgeFile);
    }

    public function test_path_outside_organization_directory_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $team = $this->team();

        $this->actingAs($admin)
            ->from(route('admin.teams.edit', $team))
            ->put(route('admin.teams.update', $team), $this->teamPayload($team, '../teams/logos/fenerbahce.png'))
            ->assertRedirect(route('admin.teams.edit', $team))
            ->assertSessionHasErrors('organization_badge');

        $this->assertNull($team->fresh()->organization_badge);
    }

    public function test_team_badge_is_rendered_in_feed_and_team_headers(): void
    {
        $team = $this->team(['organization_badge' => $this->badgePath]);
        $this->publishedPost($team);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('organization-badge--feed', false)
            ->assertSee(asset('storage/'.$this->badgePath), false);

        $this->get(route('teams.show', $team))
            ->assertOk()
            ->assertSee('organization-badge--hero', false)
            ->assertSee(asset('storage/'.$this->badgePath), false);
    }

    public function test_team_without_badge_does_not_render_empty_or_broken_image(): void
    {
        $team = $this->team();
        $this->publishedPost($team);

        $this->get(route('home'))->assertOk()->assertDontSee('organization-badge--feed', false);
        $this->get(route('teams.show', $team))->assertOk()->assertDontSee('organization-badge--hero', false);
    }

    public function test_normal_member_cannot_change_organization_badge(): void
    {
        $member = User::factory()->create();
        $team = $this->team();

        $this->actingAs($member)
            ->put(route('admin.teams.update', $team), $this->teamPayload($team, $this->badgePath))
            ->assertForbidden();

        $this->assertNull($team->fresh()->organization_badge);
    }

    public function test_admin_edit_form_discovers_badges_and_builds_readable_label(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $team = $this->team();

        $this->actingAs($admin)
            ->get(route('admin.teams.edit', $team))
            ->assertOk()
            ->assertSee('Organizasyon rozeti yok')
            ->assertSee('UEFA Şampiyonlar Ligi Test')
            ->assertSee($this->badgePath);
    }

    private function team(array $attributes = []): Team
    {
        return Team::create(array_merge([
            'name' => 'Rozet Takımı',
            'slug' => 'rozet-takimi',
            'short_name' => 'RZT',
            'primary_color' => '#172554',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
        ], $attributes));
    }

    private function teamPayload(Team $team, ?string $badge): array
    {
        return [
            'name' => $team->name,
            'slug' => $team->slug,
            'short_name' => $team->short_name,
            'primary_color' => $team->primary_color,
            'secondary_color' => $team->secondary_color,
            'status' => TeamStatus::Active->value,
            'organization_badge' => $badge,
        ];
    }

    private function publishedPost(Team $team): Post
    {
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        return Post::create([
            'team_id' => $team->id,
            'created_by' => $moderator->id,
            'type' => PostType::Text,
            'body' => 'Organizasyon rozetli takım gönderisi.',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);
    }
}
