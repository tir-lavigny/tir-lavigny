<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Event;
use App\Models\Content;
use App\Models\EventContent;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $trainingContent = Content::where('title', 'Entraînement')->first();
        $shootingContent = Content::where('title', 'Tir Obligatoire')->first();

        // Create 5 events linked to "Training"
        Event::factory(5)->create([
            'content_id' => $trainingContent->id,
        ]);

        // Create 3 events linked to "Mandatory Shooting"
        Event::factory(3)->create([
            'content_id' => $shootingContent->id,
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
