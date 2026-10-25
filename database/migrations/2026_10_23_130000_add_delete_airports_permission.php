<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    // Deleting an airport removes all of its bays - Lead Developers only
    private const ROLES = ['Lead Developer'];

    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'delete airports']);

        foreach (self::ROLES as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo('delete airports');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'delete airports')->first()?->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
