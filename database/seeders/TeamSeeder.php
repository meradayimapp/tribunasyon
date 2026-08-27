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
            ['name' => 'Fenerbahçe', 'slug' => 'fenerbahce', 'short_name' => 'FB', 'logo' => 'images/teams/logos/fenerbahce.png', 'primary_color' => '#0b2d72', 'secondary_color' => '#f3d21b'],
            ['name' => 'Galatasaray', 'slug' => 'galatasaray', 'short_name' => 'GS', 'logo' => 'images/teams/logos/galatasaray.png', 'primary_color' => '#a90432', 'secondary_color' => '#f2a900'],
            ['name' => 'Beşiktaş', 'slug' => 'besiktas', 'short_name' => 'BJK', 'logo' => 'images/teams/logos/besiktas.png', 'primary_color' => '#151515', 'secondary_color' => '#ffffff'],
            ['name' => 'Trabzonspor', 'slug' => 'trabzonspor', 'short_name' => 'TS', 'logo' => 'images/teams/logos/trabzonspor.png', 'primary_color' => '#7a1633', 'secondary_color' => '#55b8d0'],
        ];

        $coverSource = database_path('seeders/assets/stadium.png');
        if (is_file($coverSource)) {
            Storage::disk('public')->put('demo/stadium.png', file_get_contents($coverSource));
        }

        foreach ($teams as $teamData) {
            Team::updateOrCreate(['slug' => $teamData['slug']], $teamData + [
                'cover_image' => is_file($coverSource) ? 'demo/stadium.png' : null,
                'status' => TeamStatus::Active,
            ]);
        }
    }
}
