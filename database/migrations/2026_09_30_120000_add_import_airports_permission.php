<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    // Roles allowed to import new airports
    private const ROLES = ['Lead Developer', 'Developer'];

    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'import airports']);

        foreach (self::ROLES as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo('import airports');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'import airports')->first()?->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
