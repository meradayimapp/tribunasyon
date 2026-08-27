<?php

namespace Database\Seeders;

use App\Enums\TeamStatus;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $teams = [
            ['name' => 'Fenerbahçe', 'slug' => 'fenerbahce', 'short_name' => 'FB', 'primary_color' => '#0b2d72', 'secondary_color' => '#f3d21b'],
            ['name' => 'Galatasaray', 'slug' => 'galatasaray', 'short_name' => 'GS', 'primary_color' => '#a90432', 'secondary_color' => '#f2a900'],
            ['name' => 'Beşiktaş', 'slug' => 'besiktas', 'short_name' => 'BJK', 'primary_color' => '#151515', 'secondary_color' => '#ffffff'],
            ['name' => 'Trabzonspor', 'slug' => 'trabzonspor', 'short_name' => 'TS', 'primary_color' => '#7a1633', 'secondary_color' => '#55b8d0'],
        ];

        $coverSource = database_path('seeders/assets/stadium.png');
        if (is_file($coverSource)) {
            Storage::disk('public')->put('demo/stadium.png', file_get_contents($coverSource));
        }

        foreach ($teams as $teamData) {
            $logoPath = 'demo/'.$teamData['slug'].'-logo.png';
            $this->makeLogo($logoPath, $teamData['primary_color'], $teamData['secondary_color'], $teamData['short_name']);
            Team::updateOrCreate(['slug' => $teamData['slug']], $teamData + [
                'logo' => $logoPath,
                'cover_image' => is_file($coverSource) ? 'demo/stadium.png' : null,
                'status' => TeamStatus::Active,
            ]);
        }
    }

    private function makeLogo(string $path, string $background, string $foreground, string $text): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        $image = imagecreatetruecolor(256, 256);
        [$red, $green, $blue] = sscanf($background, '#%02x%02x%02x');
        [$red2, $green2, $blue2] = sscanf($foreground, '#%02x%02x%02x');
        $bg = imagecolorallocate($image, $red, $green, $blue);
        $fg = imagecolorallocate($image, $red2, $green2, $blue2);
        imagefill($image, 0, 0, $bg);
        imagefilledellipse($image, 128, 128, 205, 205, $fg);
        $width = imagefontwidth(5) * strlen($text);
        imagestring($image, 5, (int) ((256 - $width) / 2), 121, $text, $bg);
        ob_start();
        imagepng($image, null, 6);
        Storage::disk('public')->put($path, (string) ob_get_clean());
        imagedestroy($image);
    }
}
