<?php

namespace Tests\Feature;

use App\Enums\TeamStatus;
use App\Enums\UserRole;
use App\Models\SiteSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DynamicTeamsBrandingNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_new_active_team_is_in_home_communities(): void
    {
        $team = $this->team(['name' => 'Yeni Topluluk', 'slug' => 'yeni-topluluk']);

        $this->get(route('home'))
            ->assertOk()
            ->assertViewHas('suggestedTeams', fn ($teams) => $teams->contains($team));
    }

    public function test_inactive_team_is_not_in_home_communities(): void
    {
        $team = $this->team(['status' => TeamStatus::Inactive]);

        $this->get(route('home'))
            ->assertOk()
            ->assertViewHas('suggestedTeams', fn ($teams) => ! $teams->contains($team));
    }

    public function test_soft_deleted_team_is_not_in_home_communities(): void
    {
        $team = $this->team();
        $team->delete();

        $this->get(route('home'))
            ->assertOk()
            ->assertViewHas('suggestedTeams', fn ($teams) => ! $teams->contains('id', $team->id));
    }

    public function test_new_active_team_can_be_found_in_search(): void
    {
        $team = $this->team(['name' => 'Aranabilir Spor', 'slug' => 'aranabilir-spor']);

        $this->get(route('search.index', ['q' => 'Aranabilir']))
            ->assertOk()
            ->assertSee($team->name);
    }

    public function test_home_communities_are_ordered_by_sort_order_then_name(): void
    {
        $third = $this->team(['name' => 'C Takımı', 'slug' => 'c-takimi', 'sort_order' => 20]);
        $second = $this->team(['name' => 'B Takımı', 'slug' => 'b-takimi', 'sort_order' => 10]);
        $first = $this->team(['name' => 'A Takımı', 'slug' => 'a-takimi', 'sort_order' => 10]);

        $this->get(route('home'))
            ->assertOk()
            ->assertViewHas('suggestedTeams', fn ($teams) => $teams->pluck('id')->all() === [$first->id, $second->id, $third->id]);
    }

    public function test_admin_can_upload_site_logo_and_path_is_persisted(): void
    {
        $this->actingAs($this->user(UserRole::Admin))
            ->put(route('admin.settings.update'), [
                'site_name' => 'Tribünasyon',
                'site_logo' => UploadedFile::fake()->image('brand.png', 800, 240),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $settings = SiteSetting::query()->findOrFail(SiteSetting::SINGLETON_ID);

        $this->assertStringStartsWith('branding/', $settings->logo_path);
        $this->assertStringNotContainsString('brand.png', $settings->logo_path);
        Storage::disk('public')->assertExists($settings->logo_path);
        $this->get(route('home'))->assertOk()->assertSee(Storage::disk('public')->url($settings->logo_path), false);
    }

    public function test_normal_user_cannot_change_site_logo(): void
    {
        $this->actingAs($this->user(UserRole::Member))
            ->put(route('admin.settings.update'), [
                'site_name' => 'Yetkisiz değişiklik',
                'site_logo' => UploadedFile::fake()->image('forbidden.png'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_brand_fallback_is_rendered_when_no_logo_exists(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('brand-mark', false)
            ->assertSee(config('app.name'));
    }

    public function test_admin_mobile_menu_contains_admin_links(): void
    {
        $this->actingAs($this->user(UserRole::Admin))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Admin Paneli')
            ->assertSee('Organizasyonlar')
            ->assertSee('Site Ayarları');
    }

    public function test_moderator_mobile_menu_contains_moderator_links(): void
    {
        $this->actingAs($this->user(UserRole::Moderator))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Moderatör Paneli')
            ->assertSee('Gönderilerim')
            ->assertDontSee('Admin Paneli');
    }

    public function test_member_mobile_menu_does_not_contain_management_links(): void
    {
        $this->actingAs($this->user(UserRole::Member))
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('Admin Paneli')
            ->assertDontSee('Moderatör Paneli')
            ->assertDontSee('Gönderilerim');
    }

    private function team(array $attributes = []): Team
    {
        static $sequence = 0;
        $sequence++;

        return Team::create(array_merge([
            'name' => "Takım {$sequence}",
            'slug' => "takim-{$sequence}",
            'short_name' => "T{$sequence}",
            'primary_color' => '#18233f',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
            'sort_order' => 0,
        ], $attributes));
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }
}
