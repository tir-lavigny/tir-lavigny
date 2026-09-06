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
            ['email' => config('app.user.email')],
            [
                'name' => config('app.user.name'),
                'email_verified_at' => now(),
                'password' => config('app.user.password'),
            ]
        );

        $user->assignRole('admin');

        // Mailing lists
        $user->assignRole('get member registration mails');
        $user->assignRole('get register close operating day remark');
    }
}
