<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RefreshDatabase already runs 2026_07_18_120000_rename_developer_role_and_update_permissions
 * once against an empty roles table (no pre-existing 'Developer' role), so on its own that
 * only proves the "fresh install" path. The real-world case — an existing production
 * 'Developer'/'Maintainer' getting migrated — is exercised by re-invoking the migration's
 * up() against a hand-built "legacy" roles table within the test itself.
 */
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const LEAD_DEVELOPER_PERMISSIONS = [
        'edit settings', 'view users', 'view user data', 'edit user data', 'delete users',
        'approve changes', 'update status', 'view data', 'manage news', 'send notifications',
    ];

    private const OLD_MAINTAINER_PERMISSIONS = [
        'approve changes', 'update status', 'view data', 'manage news', 'send notifications',
    ];

    public function test_fresh_install_migration_creates_lead_developer_and_developer_roles(): void
    {
        $leadDeveloper = Role::where('name', 'Lead Developer')->first();
        $developer = Role::where('name', 'Developer')->first();

        $this->assertNotNull($leadDeveloper);
        $this->assertNotNull($developer);

        $this->assertEqualsCanonicalizing(self::LEAD_DEVELOPER_PERMISSIONS, $leadDeveloper->getPermissionNames()->all());
        $this->assertEqualsCanonicalizing(['view users', 'manage news', 'send notifications'], $developer->getPermissionNames()->all());
    }

    public function test_migration_renames_an_existing_production_developer_role_and_trims_maintainer(): void
    {
        // Reset to a hand-built "legacy" state, as if this migration had never run.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::query()->delete();

        $legacyDeveloper = Role::create(['name' => 'Developer']);
        $legacyDeveloper->syncPermissions(self::LEAD_DEVELOPER_PERMISSIONS);

        $legacyMaintainer = Role::create(['name' => 'Maintainer']);
        $legacyMaintainer->syncPermissions(self::OLD_MAINTAINER_PERMISSIONS);

        $migration = require database_path('migrations/2026_07_18_120000_rename_developer_role_and_update_permissions.php');
        $migration->up();

        $legacyDeveloper->refresh();
        $this->assertSame('Lead Developer', $legacyDeveloper->name);
        $this->assertEqualsCanonicalizing(self::LEAD_DEVELOPER_PERMISSIONS, $legacyDeveloper->getPermissionNames()->all());

        $newDeveloper = Role::where('name', 'Developer')->first();
        $this->assertNotNull($newDeveloper);
        $this->assertNotSame($legacyDeveloper->id, $newDeveloper->id);
        $this->assertEqualsCanonicalizing(['view users', 'manage news', 'send notifications'], $newDeveloper->getPermissionNames()->all());

        $legacyMaintainer->refresh();
        $this->assertEqualsCanonicalizing(['approve changes', 'update status', 'view data'], $legacyMaintainer->getPermissionNames()->all());
    }
}
