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
        return <<<MD
            # {$this->faker->sentence(5)}

            {$this->faker->paragraph(2)}

            ## Détails

            - {$this->faker->catchPhrase()}
            - {$this->faker->bs()}
            - {$this->faker->company()}

            [Voir l'événement]({$this->faker->url()})

            **Note Importante**: {$this->faker->sentence()}

        MD;
    }
}
