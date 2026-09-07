<?php

namespace Database\Factories;

use App\Enums\PlayerStatus;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Player> */
class PlayerFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->name();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 99999),
            'position' => fake()->randomElement(['Kaleci', 'Stoper', 'Orta Saha', 'Forvet']),
            'shirt_number' => fake()->numberBetween(1, 99),
            'birth_date' => fake()->dateTimeBetween('-38 years', '-17 years'),
            'market_value_amount' => fake()->numberBetween(100_000, 80_000_000),
            'market_value_currency' => 'EUR',
            'status' => PlayerStatus::Active,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => PlayerStatus::Inactive]);
    }
}
