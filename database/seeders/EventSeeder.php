<?php

namespace Database\Seeders;

use App\Models\Content;
use App\Models\Event;
use App\Models\EventContent;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $trainingContent = Content::where('title', 'Entraînement')->first();
        $shootingContent = Content::where('title', 'Tir Militaire')->first();
        $campagneContent = Content::where('title', 'Tir Fédéral en Campagne')->first();

        // Create 2 events today
        Event::factory(2)->create([
            'start_datetime' => now(),
        ]);

        // Create an event in the past
        $randomPastDate = now()->subDays(rand(1, 30));
        Event::factory(1)->create([
            'start_datetime' => $randomPastDate,
            'end_datetime' => $randomPastDate->addHours(2),
        ]);

        // Create 5 events linked to "Training"
        Event::factory(5)->create([
            'content_id' => $trainingContent->id,
        ]);

        // Create 3 events linked to "Mandatory Shooting"
        Event::factory(3)->create([
            'title' => 'Tir Militaire',
            'content_id' => $shootingContent->id,
        ]);

        // Create 2 events linked to "Federal Shooting in Campaign"
        Event::factory(2)->create([
            'title' => 'Tir Fédéral en Campagne',
            'content_id' => $campagneContent->id,
        ]);

        // Create 5 events without predefined content (which will have unique content)
        Event::factory(5)->create()->each(function ($event) {
            EventContent::factory()->create([
                'event_id' => $event->id,
            ]);
        });

        // Create 2 events without any content at all (no `content_id` or `event_content`)
        Event::factory(2)->create();
    }
}
