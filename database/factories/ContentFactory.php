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
        return '# '.$this->faker->sentence(4)."\n\n".
            '## '.$this->faker->sentence(3)."\n\n".
            $this->faker->paragraph(3)."\n\n".
            '- '.$this->faker->sentence()."\n".
            '- '.$this->faker->sentence()."\n".
            '- '.$this->faker->sentence()."\n\n".
            '[En savoir plus]('.$this->faker->url().")\n\n".
            '**'.$this->faker->words(5, true)."**\n\n".
            '*'.$this->faker->words(4, true).'*';
    }
}
