<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cities = [
            'Zurich',
            'Genève',
            'Lausanne',
            'Berne',
            'Lucerne',
            'Fribourg',
            'Neuchâtel',
            'Bâle',
            'Sion',
            'Lugano'
        ];

        $city = $this->faker->randomElement($cities);

        return [
            'name' => $this->faker->company . ' - ' . $city,
            'address' => $this->faker->streetAddress() . ', ' . $this->faker->postcode() . ' ' . $this->faker->city(),
            'latitude' => $this->faker->latitude(45.8, 47.8), // Suisse ~45.8° à 47.8° N
            'longitude' => $this->faker->longitude(5.9, 10.5), // Suisse ~5.9° à 10.5° E
        ];
    }
}
