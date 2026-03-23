<?php

namespace Database\Factories;

use App\Models\Content;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Content>
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
