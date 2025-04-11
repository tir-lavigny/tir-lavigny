<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Content>
 */
class ContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(3, true),
            'body' => $this->generateMarkdown(),
        ];
    }

    private function generateMarkdown(): string
    {
        return <<<MD
            # {$this->faker->sentence(4)}

            ## {$this->faker->sentence(3)}

            {$this->faker->paragraph(3)}

            - {$this->faker->sentence()}
            - {$this->faker->sentence()}
            - {$this->faker->sentence()}

            [En savoir plus]({$this->faker->url()})

            **{$this->faker->words(5, true)}**

            *{$this->faker->words(4, true)}*

        MD;
    }
}
