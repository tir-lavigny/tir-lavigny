<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Content;

class ContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Content::create([
            'title' => 'Entraînement',
            'body' => "# Entraînement\n\nCeci est le contenu pour tous les entraînements.\n\n**Important** : soyez à l'heure !",
        ]);

        Content::create([
            'title' => 'Tir Militaire',
            'body' => "### Tir Obligatoire\n\n**Programme obligatoire :**\n\n- 5 coups: coup par coup, sur cible A5\n- 5 coups: coup par coup, sur cible B4\n- 1 x 2 coups: feu de vitesse, sur cible B4\n- 1 x 3 coups: feu de vitesse, sur cible B4\n- 1 x 5 coups: feu de vitesse, sur cible B4",
        ]);

        Content::factory(3)->create(); // 3 contenus aléatoires
    }
}
