<?php

namespace Database\Seeders;

use App\Models\Content;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Content::updateOrCreate(
            ['title' => 'Entraînement'],
            ['body' => "### Entraînement\n\nLes entraînements ont lieu de 17h25 à 19h35."]
        );

        Content::updateOrCreate(
            ['title' => 'Tir Militaire'],
            ['body' => "### Tir Obligatoire\n\n**Programme obligatoire :**\n\n- 5 coups: coup par coup, sur cible A5\n- 5 coups: coup par coup, sur cible B4\n- 1 x 2 coups: feu de vitesse, sur cible B4\n- 1 x 3 coups: feu de vitesse, sur cible B4\n- 1 x 5 coups: feu de vitesse, sur cible B4"]
        );

        Content::updateOrCreate(
            ['title' => 'Tir Fédéral en Campagne'],
            ['body' => "### Tir Fédéral en Campagne\n\n- 1ère partie:\n    - Feu unique. 6 coups sont tirés dans les 6 minutes et montrés individuellement.\n\n- 2ème partie:\n    - Feu court. 3 coups sont tirés deux fois en 60 secondes chacun. Montré après tous les 3 coups.\n\n- 3ème partie:\n    - Feu rapide. 6 coups sont tirés en 60 secondes. À la fin, il sera montré."]
        );
    }
}
