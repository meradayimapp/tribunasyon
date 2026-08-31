<?php

namespace Tests\Feature\Admin;

use App\Enums\OrganizationStatus;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\TeamStatus;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManagedTeamOrganizationMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_admin_can_create_team_with_hashed_logo_on_public_disk(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.teams.store'), $this->teamPayload([
                'slug' => '',
                'logo_file' => UploadedFile::fake()->image('takim-original.png', 300, 300),
            ]))
            ->assertRedirect(route('admin.teams.index'))
            ->assertSessionHasNoErrors();

        $team = Team::where('slug', 'upload-takimi')->firstOrFail();

        $this->assertStringStartsWith('teams/logos/', $team->logo);
        $this->assertStringEndsWith('.png', $team->logo);
        $this->assertStringNotContainsString('takim-original', $team->logo);
        Storage::disk('public')->assertExists($team->logo);
    }

    public function test_admin_can_replace_existing_team_logo_and_unused_file_is_removed(): void
    {
        Storage::disk('public')->put('teams/logos/old-logo.png', 'old');
        $team = $this->team(['logo' => 'teams/logos/old-logo.png']);

        $this->actingAs($this->admin())
            ->put(route('admin.teams.update', $team), $this->teamPayload([
                'name' => $team->name,
                'slug' => $team->slug,
                'logo_file' => UploadedFile::fake()->image('replacement.png', 300, 300),
            ]))
            ->assertRedirect(route('admin.teams.index'));

        $team->refresh();
        Storage::disk('public')->assertExists($team->logo);
        Storage::disk('public')->assertMissing('teams/logos/old-logo.png');
    }

    public function test_admin_can_remove_team_logo_without_touching_unmanaged_static_asset(): void
    {
        $team = $this->team(['logo' => 'images/teams/logos/legacy.png']);

        $this->actingAs($this->admin())
            ->put(route('admin.teams.update', $team), $this->teamPayload([
                'name' => $team->name,
                'slug' => $team->slug,
                'remove_logo' => '1',
            ]))
            ->assertRedirect(route('admin.teams.index'));

        $this->assertNull($team->fresh()->logo);
    }

    public function test_unauthorized_user_cannot_change_team_logo(): void
    {
        $team = $this->team();

        $this->actingAs(User::factory()->create())
            ->put(route('admin.teams.update', $team), $this->teamPayload([
                'name' => $team->name,
                'slug' => $team->slug,
                'logo_file' => UploadedFile::fake()->image('forbidden.png'),
            ]))
            ->assertForbidden();

        $this->assertNull($team->fresh()->logo);
    }

    public function test_team_logo_with_invalid_mime_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.teams.store'), $this->teamPayload([
                'logo_file' => UploadedFile::fake()->create('fake.png', 50, 'text/plain'),
            ]))
            ->assertSessionHasErrors('logo_file');

        $this->assertDatabaseMissing('teams', ['slug' => 'upload-takimi']);
    }

    public function test_team_logo_over_five_megabytes_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.teams.store'), $this->teamPayload([
                'logo_file' => UploadedFile::fake()->image('large.png')->size(5121),
            ]))
            ->assertSessionHasErrors('logo_file');
    }

    public function test_svg_logo_is_rejected_without_a_sanitizer(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.teams.store'), $this->teamPayload([
                'logo_file' => UploadedFile::fake()->createWithContent('unsafe.svg', '<svg><script>alert(1)</script></svg>'),
            ]))
            ->assertSessionHasErrors('logo_file');
    }

    public function test_admin_can_create_organization_with_logo(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.organizations.store'), $this->organizationPayload([
                'slug' => '',
                'logo_file' => UploadedFile::fake()->image('uefa.png', 200, 200),
            ]))
            ->assertRedirect(route('admin.organizations.index'))
            ->assertSessionHasNoErrors();

        $organization = Organization::where('slug', 'uefa-test-ligi')->firstOrFail();

        $this->assertStringStartsWith('organizations/logos/', $organization->logo_path);
        $this->assertStringNotContainsString('uefa.png', $organization->logo_path);
        Storage::disk('public')->assertExists($organization->logo_path);
    }

    public function test_admin_can_change_organization_logo_and_status(): void
    {
        Storage::disk('public')->put('organizations/logos/old.png', 'old');
        $organization = $this->organization(['logo_path' => 'organizations/logos/old.png']);

        $this->actingAs($this->admin())
            ->put(route('admin.organizations.update', $organization), $this->organizationPayload([
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => OrganizationStatus::Inactive->value,
                'logo_file' => UploadedFile::fake()->image('new.png'),
            ]))
            ->assertRedirect(route('admin.organizations.index'));

        $organization->refresh();
        $this->assertSame(OrganizationStatus::Inactive, $organization->status);
        Storage::disk('public')->assertExists($organization->logo_path);
        Storage::disk('public')->assertMissing('organizations/logos/old.png');
    }

    public function test_only_admin_can_create_organization(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.organizations.store'), $this->organizationPayload([
                'logo_file' => UploadedFile::fake()->image('forbidden.png'),
            ]))
            ->assertForbidden();

        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_admin_can_assign_and_remove_team_organization(): void
    {
        $organization = $this->organization();
        $team = $this->team();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.teams.update', $team), $this->teamPayload([
                'name' => $team->name,
                'slug' => $team->slug,
                'organization_id' => $organization->id,
            ]))
            ->assertRedirect(route('admin.teams.index'));

        $this->assertSame($organization->id, $team->fresh()->organization_id);

        $this->actingAs($admin)
            ->put(route('admin.teams.update', $team), $this->teamPayload([
                'name' => $team->name,
                'slug' => $team->slug,
                'organization_id' => '',
            ]))
            ->assertRedirect(route('admin.teams.index'));

        $this->assertNull($team->fresh()->organization_id);
    }

    public function test_active_organization_badge_is_rendered_in_feed(): void
    {
        Storage::disk('public')->put('organizations/logos/active.png', 'logo');
        $organization = $this->organization(['logo_path' => 'organizations/logos/active.png']);
        $team = $this->team(['organization_id' => $organization->id]);
        $this->publishedPost($team);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('organization-badge--feed', false)
            ->assertSee($organization->logoUrl(), false);
    }

    public function test_team_without_organization_does_not_render_broken_badge(): void
    {
        $team = $this->team();
        $this->publishedPost($team);

        $this->get(route('home'))->assertOk()->assertDontSee('organization-badge--feed', false);
    }

    public function test_inactive_organization_is_hidden_from_new_team_selection(): void
    {
        $active = $this->organization(['name' => 'Aktif Avrupa Ligi', 'slug' => 'aktif-avrupa']);
        $inactive = $this->organization([
            'name' => 'Pasif Avrupa Ligi',
            'slug' => 'pasif-avrupa',
            'status' => OrganizationStatus::Inactive,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.teams.create'))
            ->assertOk()
            ->assertSee($active->name)
            ->assertDontSee($inactive->name);
    }

    public function test_current_inactive_organization_remains_visible_on_team_edit(): void
    {
        $inactive = $this->organization([
            'name' => 'Mevcut Pasif Organizasyon',
            'slug' => 'mevcut-pasif',
            'status' => OrganizationStatus::Inactive,
        ]);
        $team = $this->team(['organization_id' => $inactive->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.teams.edit', $team))
            ->assertOk()
            ->assertSee($inactive->name)
            ->assertSee('Pasif — mevcut seçim');
    }

    public function test_deleting_organization_detaches_teams_and_removes_managed_logo(): void
    {
        Storage::disk('public')->put('organizations/logos/delete.png', 'logo');
        $organization = $this->organization(['logo_path' => 'organizations/logos/delete.png']);
        $team = $this->team(['organization_id' => $organization->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.organizations.destroy', $organization))
            ->assertRedirect(route('admin.organizations.index'));

        $this->assertNull($team->fresh()->organization_id);
        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
        Storage::disk('public')->assertMissing('organizations/logos/delete.png');
    }

    public function test_legacy_badge_column_and_existing_four_team_seed_are_preserved(): void
    {
        $this->assertTrue(Schema::hasColumn('teams', 'organization_badge'));
        $this->assertTrue(Schema::hasColumn('teams', 'organization_id'));

        $this->seed(TeamSeeder::class);

        foreach (['fenerbahce', 'galatasaray', 'besiktas', 'trabzonspor'] as $slug) {
            $this->assertDatabaseHas('teams', ['slug' => $slug]);
        }
    }

    public function test_legacy_badge_path_is_backfilled_into_organization_relationship(): void
    {
        $migration = require database_path('migrations/2026_08_31_000001_create_organizations_and_link_teams.php');
        $migration->down();

        $team = Team::create([
            'name' => 'Legacy Rozet Takımı',
            'slug' => 'legacy-rozet-takimi',
            'short_name' => 'LRT',
            'primary_color' => '#172554',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
            'organization_badge' => 'organizations-images/legacy-avrupa.png',
        ]);

        $migration->up();
        $team->refresh();

        $this->assertNotNull($team->organization_id);
        $this->assertDatabaseHas('organizations', [
            'id' => $team->organization_id,
            'logo_path' => 'organizations-images/legacy-avrupa.png',
            'status' => OrganizationStatus::Active->value,
        ]);
        $this->assertSame('organizations-images/legacy-avrupa.png', $team->organization->logo_path);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function team(array $attributes = []): Team
    {
        return Team::create(array_merge([
            'name' => 'Mevcut Takım',
            'slug' => 'mevcut-takim',
            'short_name' => 'MVT',
            'primary_color' => '#172554',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
        ], $attributes));
    }

    private function organization(array $attributes = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'UEFA Test Ligi',
            'slug' => 'uefa-test-ligi',
            'logo_path' => null,
            'status' => OrganizationStatus::Active,
        ], $attributes));
    }

    private function teamPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Upload Takımı',
            'slug' => 'upload-takimi',
            'short_name' => 'UPL',
            'primary_color' => '#172554',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active->value,
            'organization_id' => '',
        ], $overrides);
    }

    private function organizationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'UEFA Test Ligi',
            'slug' => 'uefa-test-ligi',
            'status' => OrganizationStatus::Active->value,
        ], $overrides);
    }

    private function publishedPost(Team $team): Post
    {
        return Post::create([
            'team_id' => $team->id,
            'created_by' => User::factory()->create()->id,
            'type' => PostType::Text,
            'body' => 'Organizasyon rozetli takım gönderisi.',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);
    }
}
