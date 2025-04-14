<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
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
