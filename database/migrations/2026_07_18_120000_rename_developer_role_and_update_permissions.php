<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const LEAD_DEVELOPER_PERMISSIONS = [
        'edit settings',
        'view users',
        'view user data',
        'edit user data',
        'delete users',
        'approve changes',
        'update status',
        'view data',
        'manage news',
        'send notifications',
    ];

    private const DEVELOPER_PERMISSIONS = [
        'view users',
        'manage news',
        'send notifications',
    ];

    private const MAINTAINER_PERMISSIONS = [
        'approve changes',
        'update status',
        'view data',
    ];

    private const OLD_MAINTAINER_PERMISSIONS = [
        'approve changes',
        'update status',
        'view data',
        'manage news',
        'send notifications',
    ];

    public function up(): void
    {
        // Permissions are normally seeded by DatabaseSeeder, but this migration must also work
        // standalone (e.g. against a freshly migrated-but-not-seeded database), so make sure
        // every permission it references exists before assigning any of them to a role.
        foreach (array_unique(array_merge(self::LEAD_DEVELOPER_PERMISSIONS, self::DEVELOPER_PERMISSIONS, self::MAINTAINER_PERMISSIONS)) as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $leadDeveloper = Role::where('name', 'Developer')->first();
        if ($leadDeveloper !== null) {
            $leadDeveloper->name = 'Lead Developer';
            $leadDeveloper->save();
        } else {
            $leadDeveloper = Role::firstOrCreate(['name' => 'Lead Developer']);
        }
        $leadDeveloper->syncPermissions(self::LEAD_DEVELOPER_PERMISSIONS);

        $developer = Role::firstOrCreate(['name' => 'Developer']);
        $developer->syncPermissions(self::DEVELOPER_PERMISSIONS);

        $maintainer = Role::where('name', 'Maintainer')->first();
        if ($maintainer !== null) {
            $maintainer->syncPermissions(self::MAINTAINER_PERMISSIONS);
        }
    }

    public function down(): void
    {
        $developer = Role::where('name', 'Developer')->first();
        $developer?->delete();

        $leadDeveloper = Role::where('name', 'Lead Developer')->first();
        if ($leadDeveloper !== null) {
            $leadDeveloper->name = 'Developer';
            $leadDeveloper->save();
        }

        $maintainer = Role::where('name', 'Maintainer')->first();
        if ($maintainer !== null) {
            $maintainer->syncPermissions(self::OLD_MAINTAINER_PERMISSIONS);
        }
    }
};
