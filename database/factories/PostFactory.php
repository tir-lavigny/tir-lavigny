<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'content' => $this->generateMarkdown(),
            'publish_at' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'expire_at' => fake()->boolean() ? fake()->dateTimeBetween('+1 month', '+3 months') : null,
        ];
    }

    private function generateMarkdown(): string
    {
        return '# '.fake()->sentence(5)."\n\n".

            fake()->paragraph(2)."\n\n".

            '> '.fake()->catchPhrase()."\n\n".

            '- '.fake()->bs()."\n".
            '- '.fake()->bs()."\n".
            '- '.fake()->company()."\n\n".

            "[Voir l'événement](".fake()->url().")\n\n".
            '**Note Importante**: '.fake()->sentence()."\n";
    }
}
