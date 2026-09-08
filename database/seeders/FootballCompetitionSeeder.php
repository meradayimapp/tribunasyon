<?php

namespace Database\Seeders;

use App\Models\FootballCompetition;
use Illuminate\Database\Seeder;

class FootballCompetitionSeeder extends Seeder
{
    public function run(): void
    {
        $competitions = [
            [
                'provider_league_id' => '482ofyysbdbeoxauk19yg7tdt',
                'name' => 'Trendyol Süper Lig',
                'display_name' => 'Trendyol Süper Lig',
                'slug' => 'super-lig',
                'country' => 'Türkiye',
                'sort_order' => 10,
            ],
            [
                'provider_league_id' => '4oogyu6o156iphvdvphwpck10',
                'name' => 'UEFA Champions League',
                'display_name' => 'Şampiyonlar Ligi',
                'slug' => 'sampiyonlar-ligi',
                'country' => null,
                'sort_order' => 20,
            ],
            [
                'provider_league_id' => '4c1nfi2j1m731hcay25fcgndq',
                'name' => 'UEFA Europa League',
                'display_name' => 'Avrupa Ligi',
                'slug' => 'avrupa-ligi',
                'country' => null,
                'sort_order' => 30,
            ],
            [
                'provider_league_id' => 'c7b8o53flg36wbuevfzy3lb10',
                'name' => 'UEFA Conference League',
                'display_name' => 'Konferans Ligi',
                'slug' => 'konferans-ligi',
                'country' => null,
                'sort_order' => 40,
            ],
        ];

        foreach ($competitions as $competition) {
            FootballCompetition::updateOrCreate(
                [
                    'provider' => 'live-football-api',
                    'provider_league_id' => $competition['provider_league_id'],
                ],
                $competition + [
                    'timezone' => 'UTC',
                    'is_active' => true,
                ],
            );
        }
    }
}
