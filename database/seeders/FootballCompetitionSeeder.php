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
                'provider_league_id' => FootballCompetition::SUPER_LEAGUE_PROVIDER_ID,
                'name' => 'Trendyol Süper Lig',
                'display_name' => 'Trendyol Süper Lig',
                'slug' => 'super-lig',
                'country' => 'Türkiye',
                'sort_order' => 10,
            ],
            [
                'provider_league_id' => FootballCompetition::NATIONS_LEAGUE_PROVIDER_ID,
                'name' => 'UEFA Nations League',
                'display_name' => 'Uluslar Ligi',
                'slug' => 'uluslar-ligi',
                'country' => null,
                'current_season' => '2026/2027',
                'sort_order' => 15,
            ],
            [
                'provider_league_id' => FootballCompetition::CHAMPIONS_LEAGUE_PROVIDER_ID,
                'name' => 'UEFA Champions League',
                'display_name' => 'Şampiyonlar Ligi',
                'slug' => 'sampiyonlar-ligi',
                'country' => null,
                'sort_order' => 20,
            ],
            [
                'provider_league_id' => FootballCompetition::EUROPA_LEAGUE_PROVIDER_ID,
                'name' => 'UEFA Europa League',
                'display_name' => 'Avrupa Ligi',
                'slug' => 'avrupa-ligi',
                'country' => null,
                'sort_order' => 30,
            ],
            [
                'provider_league_id' => FootballCompetition::CONFERENCE_LEAGUE_PROVIDER_ID,
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
