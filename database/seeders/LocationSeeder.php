<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Location;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            [
                'name' => 'Stand de Tir de Lausanne',
                'address' => 'Chemin du Stand 1, 1000 Lausanne',
                'latitude' => 46.5197,
                'longitude' => 6.6323,
            ],
            [
                'name' => 'Stand de Tir de Genève',
                'address' => 'Route de Chancy 98, 1213 Onex',
                'latitude' => 46.2044,
                'longitude' => 6.1432,
            ],
            [
                'name' => 'Stand de Tir de Berne',
                'address' => 'Wankdorffeldstrasse 102, 3014 Bern',
                'latitude' => 46.9481,
                'longitude' => 7.4474,
            ],
            [
                'name' => 'Stand de Tir de Sion',
                'address' => 'Rue des Casernes 45, 1950 Sion',
                'latitude' => 46.2276,
                'longitude' => 7.3606,
            ],
            [
                'name' => 'Stand de Tir de Fribourg',
                'address' => 'Route des Arsenaux 15, 1700 Fribourg',
                'latitude' => 46.8065,
                'longitude' => 7.1619,
            ],
        ];

        foreach ($locations as $location) {
            Location::updateOrCreate(['name' => $location['name']], $location);
        }
    }
}
