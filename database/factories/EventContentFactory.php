<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventContent>
 */
class EventContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => $this->generateMarkdown(),
        ];
    }

    private function generateMarkdown(): string
    {
        return "# " . $this->faker->sentence(5) . "\n\n" .
            $this->faker->paragraph(2) . "\n\n" .
            "## Détails\n\n" .
            "- " . $this->faker->catchPhrase() . "\n" .
            "- " . $this->faker->bs() . "\n" .
            "- " . $this->faker->company() . "\n\n" .
            "[Voir l'événement](" . $this->faker->url() . ")\n\n" .
            "**Note Importante**: " . $this->faker->sentence() . "\n";
    }
}
