<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UiIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_chrome_icon_is_a_decorative_thin_outline_svg(): void
    {
        $icon = Blade::render('<x-ui.icon name="search" />');

        $this->assertStringContainsString('fill="none"', $icon);
        $this->assertStringContainsString('stroke="currentColor"', $icon);
        $this->assertStringContainsString('stroke-width="1.8"', $icon);
        $this->assertStringContainsString('aria-hidden="true"', $icon);
        $this->assertStringContainsString('focusable="false"', $icon);
    }

    public function test_global_icon_buttons_have_accessible_names(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="Ara"', false)
            ->assertSee('data-theme-toggle aria-label=', false)
            ->assertSee('aria-label="Hesap menüsünü aç"', false);
    }
}
