<?php

namespace Database\Seeders;

use App\Models\Content;
use App\Models\Event;
use App\Models\EventContent;
use App\Models\Location;
use Illuminate\Database\Seeder;

class Data2025Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createContents();
        $this->createLocations();
        $this->createEvents();
    }

    private function createEvents()
    {
        $trainingContent = Content::where('title', 'Entraînement 2025')->first();
        $shootingContent = Content::where('title', 'Tir Militaire 2025')->first();
        $campagneContent = Content::where('title', 'Tir Fédéral en Campagne 2025')->first();
        $closingContent = Content::where('title', 'Tir de clôture 2025')->first();

        $lavignyLocation = Location::where('name', 'Stand de tir de Lavigny')->first();
        $villarsLocation = Location::where('name', 'Stand de Villars-sous-Yens')->first();
        $etoyLocation = Location::where('name', 'Stand de tir d\'Etoy')->first();

        $e1 = Event::create([
            'title' => '1er entraînement',
            'start_datetime' => '2025-03-27 17:25:00',
            'end_datetime' => '2025-03-27 19:35:00',
        ]);
        $e1->location()->associate($lavignyLocation);
        $e1->content()->associate($trainingContent);
        $e1->save();

        $e2 = Event::create([
            'title' => 'Début: Pause estivale',
            'start_datetime' => '2025-07-03 19:35:00',
        ]);
        $c2 = new EventContent([
            'body' => "### Stand de tir fermé\r\n\r\n**Dernier entraînement :** Jeudi 03 juillet\r\n\r\n**Reprise des entraînements :** Jeudi 14 août",
        ]);
        $e2->customContent()->save($c2);
        $e2->location()->associate($lavignyLocation);
        $e2->save();

        $e3 = Event::create([
            'title' => 'Fin: Pause estivale',
            'start_datetime' => '2025-08-14 17:25:00',
        ]);
        $c3 = new EventContent([
            'body' => '### Stand de tir ouvert',
        ]);
        $e3->customContent()->save($c3);
        $e3->location()->associate($lavignyLocation);
        $e3->save();

        $e4 = Event::create([
            'title' => 'Fin des entraînements',
            'start_datetime' => '2025-10-02 19:35:00',
        ]);
        $c4 = new EventContent([
            'body' => '### Fin de la saison 2025',
        ]);
        $e4->customContent()->save($c4);
        $e4->location()->associate($lavignyLocation);
        $e4->save();

        $e5 = Event::create([
            'title' => 'Tir militaire',
            'start_datetime' => '2025-04-10 17:25:00',
            'end_datetime' => '2025-04-10 19:35:00',
        ]);
        $e5->location()->associate($lavignyLocation);
        $e5->content()->associate($shootingContent);
        $e5->save();

        $e6 = Event::create([
            'title' => 'Tir militaire',
            'start_datetime' => '2025-05-15 17:25:00',
            'end_datetime' => '2025-05-15 19:35:00',
        ]);
        $e6->location()->associate($lavignyLocation);
        $e6->content()->associate($shootingContent);
        $e6->save();

        $e7 = Event::create([
            'title' => 'Tir militaire',
            'start_datetime' => '2025-06-26 17:25:00',
            'end_datetime' => '2025-06-26 19:35:00',
        ]);
        $e7->location()->associate($lavignyLocation);
        $e7->content()->associate($shootingContent);
        $e7->save();

        $e8 = Event::create([
            'title' => 'Tir militaire',
            'start_datetime' => '2025-08-28 17:25:00',
            'end_datetime' => '2025-08-28 19:35:00',
        ]);
        $e8->location()->associate($lavignyLocation);
        $e8->content()->associate($shootingContent);
        $e8->save();

        $e9 = Event::create([
            'title' => 'Tir de clôture',
            'start_datetime' => '2025-10-18 09:00:00',
            'end_datetime' => '2025-10-18 12:00:00',
        ]);
        $e9->location()->associate($lavignyLocation);
        $e9->content()->associate($closingContent);
        $e9->save();

        $e10 = Event::create([
            'title' => 'Tir de clôture',
            'start_datetime' => '2025-10-18 13:30:00',
            'end_datetime' => '2025-10-18 17:00:00',
        ]);
        $e10->location()->associate($lavignyLocation);
        $e10->content()->associate($closingContent);
        $e10->save();

        $e11 = Event::create([
            'title' => 'Tir fédéral en campagne',
            'start_datetime' => '2025-05-22 14:20:00',
            'end_datetime' => '2025-05-22 20:00:00',
        ]);
        $e11->location()->associate($villarsLocation);
        $e11->content()->associate($campagneContent);
        $e11->save();

        $e12 = Event::create([
            'title' => 'Tir fédéral en campagne',
            'start_datetime' => '2025-05-23 16:20:00',
            'end_datetime' => '2025-05-23 20:00:00',
        ]);
        $e12->location()->associate($villarsLocation);
        $e12->content()->associate($campagneContent);
        $e12->save();

        $e13 = Event::create([
            'title' => 'Tir fédéral en campagne',
            'start_datetime' => '2025-05-24 08:00:00',
            'end_datetime' => '2025-05-24 11:30:00',
        ]);
        $e13->location()->associate($villarsLocation);
        $e13->content()->associate($campagneContent);
        $e13->save();

        $e14 = Event::create([
            'title' => 'Tir fédéral en campagne',
            'start_datetime' => '2025-05-24 13:30:00',
            'end_datetime' => '2025-05-24 16:00:00',
        ]);
        $e14->location()->associate($villarsLocation);
        $e14->content()->associate($campagneContent);
        $e14->save();
    }

    private function createContents()
    {
        Content::updateOrCreate(
            ['title' => 'Entraînement 2025'],
            ['body' => "### Entraînement\n\nLes entraînements ont lieu de 17h25 à 19h35."]
        );

        Content::updateOrCreate(
            ['title' => 'Tir Militaire 2025'],
            ['body' => "### Tir Obligatoire\n\n**Programme obligatoire :**\n\n- 5 coups: coup par coup, sur cible A5\n- 5 coups: coup par coup, sur cible B4\n- 1 x 2 coups: feu de vitesse, sur cible B4\n- 1 x 3 coups: feu de vitesse, sur cible B4\n- 1 x 5 coups: feu de vitesse, sur cible B4"]
        );

        Content::updateOrCreate(
            ['title' => 'Tir Fédéral en Campagne 2025'],
            ['body' => "### Tir Fédéral en Campagne\n\n- 1ère partie:\n    - Feu unique. 6 coups sont tirés dans les 6 minutes et montrés individuellement.\n\n- 2ème partie:\n    - Feu court. 3 coups sont tirés deux fois en 60 secondes chacun. Montré après tous les 3 coups.\n\n- 3ème partie:\n    - Feu rapide. 6 coups sont tirés en 60 secondes. À la fin, il sera montré."]
        );

        Content::updateOrCreate(
            ['title' => 'Tir de clôture 2025'],
            ['body' => "### Tir de clôture\n\nMerci a toutes et tous pour votre participation durant cette saison 2025."]
        );
    }

    private function createLocations()
    {
        $locations = [
            [
                'name' => 'Stand de tir de Lavigny',
                'address' => 'GC2C+J3, 1175 Lavigny',
                'latitude' => 46.501453,
                'longitude' => 6.419919,
            ],
            [
                'name' => 'Stand de Villars-sous-Yens',
                'address' => '1168 Villars-sous-Yens',
                'latitude' => 46.506389,
                'longitude' => 6.428145,
            ],
            [
                'name' => 'Stand de tir d\'Etoy',
                'address' => 'Rte du Stand 45, 1163 Etoy',
                'latitude' => 46.477870,
                'longitude' => 6.407862,
            ],
        ];

        foreach ($locations as $location) {
            Location::updateOrCreate(['name' => $location['name']], $location);
        }
    }
}
