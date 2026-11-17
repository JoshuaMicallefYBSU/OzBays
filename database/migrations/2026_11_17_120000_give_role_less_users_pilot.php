<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Users who logged in but never opened their dashboard were never given a role - make them Pilots.
     */
    public function up(): void
    {
        if (! Role::where('name', 'Pilot')->exists()) {
            return;
        }

        User::doesntHave('roles')->each(fn (User $user) => $user->assignRole('Pilot'));

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Nothing to undo - Pilot is the default role
    }
};
