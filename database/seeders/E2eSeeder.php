<?php

namespace Database\Seeders;

use App\Models\Content;
use App\Models\Event;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        $this->createLocations();
        $this->createContents();
        $this->createEvents();
        $this->createAdminUser();
    }

    private function createLocations(): void
    {
        Location::updateOrCreate(
            ['name' => 'Stand de tir de Lavigny'],
            ['address' => 'GC2C+J3, 1175 Lavigny', 'latitude' => 46.501453, 'longitude' => 6.419919],
        );
        Location::updateOrCreate(
            ['name' => 'Stand de Villars-sous-Yens'],
            ['address' => '1168 Villars-sous-Yens', 'latitude' => 46.506389, 'longitude' => 6.428145],
        );
    }

    private function createContents(): void
    {
        Content::updateOrCreate(
            ['title' => 'Entraînement'],
            ['body' => "### Entraînement\n\nLes entraînements ont lieu de 17h25 à 19h35."],
        );
        Content::updateOrCreate(
            ['title' => 'Tir Militaire'],
            ['body' => "### Tir Obligatoire\n\nProgramme standard."],
        );
    }

    private function createEvents(): void
    {
        $now = Carbon::now('Europe/Zurich');
        $lavigny = Location::where('name', 'Stand de tir de Lavigny')->first();
        $training = Content::where('title', 'Entraînement')->first();
        $military = Content::where('title', 'Tir Militaire')->first();

        // ── Past event (ended 5 days ago) ──
        $pastStart = (clone $now)->subDays(5)->subHours(2)->setTime(17, 25);
        $this->createEvent('Événement passé', $pastStart, (clone $pastStart)->addHours(2), $lavigny, $training);

        // ── Current event (started 1h ago, ends in 2h) ──
        $currentStart = (clone $now)->subHour();
        $this->createEvent('Événement en cours', $currentStart, (clone $now)->addHours(2), $lavigny, $military);

        // ── Future event (starts in 14 days at 17:25) ──
        $futureStart = (clone $now)->addDays(14)->setTime(17, 25);
        $this->createEvent('Événement futur', $futureStart, (clone $futureStart)->addHours(2), $lavigny, $military);

        // ── Tonight's closing event (ends tonight) ──
        $tonightEnd = (clone $now)->endOfDay();
        $tonightStart = (clone $tonightEnd)->subHours(2);
        $this->createEvent('Clôture soirée', $tonightStart, $tonightEnd, $lavigny, $military);
    }

    private function createEvent(
        string $title,
        Carbon $startLocal,
        Carbon $endLocal,
        Location $location,
        Content $content,
    ): void {
        // Convert local Zurich time → UTC (matches app pattern)
        $startUtc = $startLocal->copy()->timezone('UTC')->toDateTimeString();
        $endUtc = $endLocal->copy()->timezone('UTC')->toDateTimeString();

        $event = Event::create([
            'title' => $title,
            'start_datetime' => $startUtc,
            'end_datetime' => $endUtc,
            'content_id' => $content->id,
            'location_id' => $location->id,
        ]);
    }

    private function createAdminUser(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@tir-lavigny.ch'],
            [
                'name' => 'Test Admin',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
        )->assignRole('admin');
    }
}
