<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        $user = User::updateOrCreate(
            ['email' => env('APP_USER_EMAIL')],
            [
                'name' => env('APP_USER_NAME'),
                'email_verified_at' => now(),
                'password' => env('APP_USER_PASSWORD'),
            ]
        );

        $user->assignRole('admin');

        $this->call([
            Data2025Seeder::class,
        ]);
    }
}
