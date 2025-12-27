<?php

namespace Database\Seeders;

use App\Models\Users\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Clear all Tables before Seeding - Thus allowing the seeder to work as intended
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('role_has_permissions')->truncate();
        DB::table('roles')->truncate();
        DB::table('permissions')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        ### Create the assignable roles
        $developer = Role::create(['id' => 1, 'name' => 'Developer']);
        $maintainer = Role::create(['id' => 2, 'name' => 'Maintainer']);
        $contributor = Role::create(['id' => 3, 'name' => 'Contributor']);
        $member = Role::create(['id' => 99, 'name' => 'Member']);

        ### Create permissions...
        // System
        Permission::create(['name' => 'edit settings']);

        // Users
        Permission::create(['name' => 'view users']);
        Permission::create(['name' => 'view user data']);
        Permission::create(['name' => 'edit user data']);
        Permission::create(['name' => 'delete users']);

        // Airport & Aircraft Data
        Permission::create(['name' => 'approve changes']);
        Permission::create(['name' => 'view data']);


        $developer->syncPermissions([
            'edit settings',

            'view users',
            'view user data',
            'edit user data',
            'delete users',

            'approve changes',
            'view data'
        ]);

        $maintainer->syncPermissions([
            'approve changes',

            'view data'
        ]);
        $contributor->syncPermissions([
            'view data'
        ]);
        $member->syncPermissions([]);
    }
}
