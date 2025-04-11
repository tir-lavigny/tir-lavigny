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
            'title' => 'Tir Obligatoire',
            'body' => "# Tir Obligatoire\n\nLe tir obligatoire est prévu selon les règles officielles.\n\n- Date : à vérifier\n- Lieu : stand de tir officiel",
        ]);

        Content::factory(3)->create(); // 3 contenus aléatoires
    }
}
