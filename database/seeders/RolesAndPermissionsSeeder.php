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
        $adminRole = Role::create(['name' => 'admin']);
        $editorRole = Role::create(['name' => 'editor']);
        $userRole = Role::create(['name' => 'user']);

        $createEventPermission = Permission::create(['name' => 'create event']);
        $editEventPermission = Permission::create(['name' => 'edit event']);
        $deleteEventPermission = Permission::create(['name' => 'delete event']);

        $createLocationPermission = Permission::create(['name' => 'create location']);
        $editLocationPermission = Permission::create(['name' => 'edit location']);
        $deleteLocationPermission = Permission::create(['name' => 'delete location']);

        $createContentPermission = Permission::create(['name' => 'create content']);
        $editContentPermission = Permission::create(['name' => 'edit content']);
        $deleteContentPermission = Permission::create(['name' => 'delete content']);

        $createUserPermission = Permission::create(['name' => 'create user']);
        $editUserPermission = Permission::create(['name' => 'edit user']);
        $deleteUserPermission = Permission::create(['name' => 'delete user']);

        // Assign permissions to roles
        $adminRole->givePermissionTo([
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

        $editorRole->givePermissionTo([
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
