<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $editorRole = Role::firstOrCreate(['name' => 'editor']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        $createEventPermission = Permission::firstOrCreate(['name' => 'create event']);
        $editEventPermission = Permission::firstOrCreate(['name' => 'edit event']);
        $deleteEventPermission = Permission::firstOrCreate(['name' => 'delete event']);

        $createLocationPermission = Permission::firstOrCreate(['name' => 'create location']);
        $editLocationPermission = Permission::firstOrCreate(['name' => 'edit location']);
        $deleteLocationPermission = Permission::firstOrCreate(['name' => 'delete location']);

        $createContentPermission = Permission::firstOrCreate(['name' => 'create content']);
        $editContentPermission = Permission::firstOrCreate(['name' => 'edit content']);
        $deleteContentPermission = Permission::firstOrCreate(['name' => 'delete content']);

        $createUserPermission = Permission::firstOrCreate(['name' => 'create user']);
        $editUserPermission = Permission::firstOrCreate(['name' => 'edit user']);
        $deleteUserPermission = Permission::firstOrCreate(['name' => 'delete user']);

        // Assign permissions to roles
        $adminRole->syncPermissions([
            $createEventPermission,
            $editEventPermission,
            $deleteEventPermission,
            $createLocationPermission,
            $editLocationPermission,
            $deleteLocationPermission,
            $createContentPermission,
            $editContentPermission,
            $deleteContentPermission,
            $createUserPermission,
            $editUserPermission,
            $deleteUserPermission,
        ]);

        $editorRole->syncPermissions([
            $createEventPermission,
            $editEventPermission,
            $deleteEventPermission,
            $createLocationPermission,
            $editLocationPermission,
            $deleteLocationPermission,
            $createContentPermission,
            $editContentPermission,
            $deleteContentPermission,
        ]);
    }
}
