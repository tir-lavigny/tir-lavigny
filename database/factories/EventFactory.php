<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+1 month');

        return [
            'title' => $this->faker->sentence(4),
            'start_datetime' => $start,
            'end_datetime' => (clone $start)->modify('+2 hours'),
            'content_id' => null, // on ne le mettra que parfois dans le seeder
        ];
    }
}
