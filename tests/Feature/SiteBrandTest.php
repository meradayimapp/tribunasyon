<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteBrandTest extends TestCase
{
    public function test_mobile_small_logo_has_readable_aspect_ratio_safe_dimensions_without_changing_desktop_logo(): void
    {
        $styles = file_get_contents(resource_path('scss/app.scss'));

        $this->assertIsString($styles);
        $this->assertStringContainsString('.site-brand-full { width: auto; max-width: 154px; height: 38px;', $styles);
        $this->assertStringContainsString('.site-brand--mobile .site-brand-compact { width: clamp(88px, 24vw, 110px); max-height: 32px; height: auto; object-fit: contain; object-position: left center; }', $styles);
    }

    #[DataProvider('logoCases')]
    public function test_logo_resolution_and_rendering(array $paths, ?string $light, ?string $dark, bool $compact): void
    {
        Storage::fake('public');
        foreach (array_filter($paths) as $path) {
            if ($path !== 'branding/missing.png') {
                Storage::disk('public')->put($path, 'image fixture');
            }
        }

        $settings = new SiteSetting(['site_name' => 'Tribünasyon', ...$paths]);
        $variant = $compact ? 'mobile' : 'desktop';
        $html = Blade::render('<x-site-brand :settings="$settings" :variant="$variant" />', compact('settings', 'variant'));

        foreach (['light' => $light, 'dark' => $dark] as $theme => $expected) {
            $url = $expected ? Storage::disk('public')->url($expected) : null;
            $this->assertSame($url, $settings->themeLogoUrl($theme, $compact));
            if ($url) {
                $this->assertStringContainsString('src="'.$url.'"', $html);
                $this->assertStringNotContainsString('brand-mark', $html);
            } else {
                $this->assertStringContainsString('brand-mark', $html);
                $this->assertStringContainsString('Tribünasyon', $html);
                $this->assertStringNotContainsString('<img', $html);
            }
        }
    }

    public static function logoCases(): array
    {
        return [
            'mobile uses site logo without small logo' => [['logo_path' => 'branding/site.png'], 'branding/site.png', 'branding/site.png', true],
            'mobile prefers small over site and themes' => [['small_logo_path' => 'branding/small.png', 'logo_path' => 'branding/site.png', 'light_logo_path' => 'branding/light.png', 'dark_logo_path' => 'branding/dark.png'], 'branding/small.png', 'branding/small.png', true],
            'mobile selects each theme' => [['logo_path' => 'branding/site.png', 'light_logo_path' => 'branding/light.png', 'dark_logo_path' => 'branding/dark.png'], 'branding/light.png', 'branding/dark.png', true],
            'mobile light falls back to site' => [['logo_path' => 'branding/site.png', 'dark_logo_path' => 'branding/dark.png'], 'branding/site.png', 'branding/dark.png', true],
            'mobile missing small file falls back to site' => [['small_logo_path' => 'branding/missing.png', 'logo_path' => 'branding/site.png'], 'branding/site.png', 'branding/site.png', true],
            'mobile default' => [[], null, null, true],
            'mobile missing custom file uses default' => [['logo_path' => 'branding/missing.png'], null, null, true],
            'desktop themes still win over small and site' => [['small_logo_path' => 'branding/small.png', 'logo_path' => 'branding/site.png', 'light_logo_path' => 'branding/light.png', 'dark_logo_path' => 'branding/dark.png'], 'branding/light.png', 'branding/dark.png', false],
            'desktop site fallback' => [['logo_path' => 'branding/site.png'], 'branding/site.png', 'branding/site.png', false],
            'desktop alternate theme fallback preserved' => [['dark_logo_path' => 'branding/dark.png'], 'branding/dark.png', 'branding/dark.png', false],
            'desktop default' => [[], null, null, false],
        ];
    }
}
