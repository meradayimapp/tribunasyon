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
            [
                'provider_league_id' => FootballCompetition::PREMIER_LEAGUE_PROVIDER_ID,
                'name' => 'Premier Lig',
                'display_name' => 'Premier League',
                'slug' => 'premier-league',
                'country' => 'İngiltere',
                'current_season' => '2026/2027',
                'sort_order' => 50,
            ],
            [
                'provider_league_id' => FootballCompetition::LA_LIGA_PROVIDER_ID,
                'name' => 'LaLiga',
                'display_name' => 'La Liga',
                'slug' => 'la-liga',
                'country' => 'İspanya',
                'current_season' => '2026/2027',
                'sort_order' => 60,
            ],
            [
                'provider_league_id' => FootballCompetition::SERIE_A_PROVIDER_ID,
                'name' => 'Serie A',
                'display_name' => 'Serie A',
                'slug' => 'serie-a',
                'country' => 'İtalya',
                'current_season' => '2026/2027',
                'sort_order' => 70,
            ],
            [
                'provider_league_id' => FootballCompetition::BUNDESLIGA_PROVIDER_ID,
                'name' => 'Bundesliga',
                'display_name' => 'Bundesliga',
                'slug' => 'bundesliga',
                'country' => 'Almanya',
                'current_season' => '2026/2027',
                'sort_order' => 80,
            ],
            [
                'provider_league_id' => FootballCompetition::LIGUE_1_PROVIDER_ID,
                'name' => 'Ligue 1',
                'display_name' => 'Ligue 1',
                'slug' => 'ligue-1',
                'country' => 'Fransa',
                'current_season' => '2026/2027',
                'sort_order' => 90,
            ],
            [
                'provider_league_id' => FootballCompetition::PRIMEIRA_LIGA_PROVIDER_ID,
                'name' => 'Primeira Liga',
                'display_name' => 'Primeira Liga',
                'slug' => 'primeira-liga',
                'country' => 'Portekiz',
                'current_season' => '2026/2027',
                'sort_order' => 100,
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
