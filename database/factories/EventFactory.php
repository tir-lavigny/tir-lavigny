<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
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

        $hasLocation = $this->faker->boolean(50); // 50% chance to have a location

        return [
            'title' => $this->faker->sentence(4),
            'start_datetime' => $start,
            'end_datetime' => (clone $start)->modify('+2 hours'),
            'content_id' => null,
            'location_id' => $hasLocation ? Location::inRandomOrder()->first()?->id : null,
        ];
    }
}
