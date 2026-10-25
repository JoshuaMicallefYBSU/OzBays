<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    // One row per counted visit to a tracked page (home/map/airport_ladder) - deduped to at
    // most one per visitor per 15 minutes by TrackPageView, so a row here always represents
    // a genuinely new visit, not a page refresh or AJAX poll.
    private const VIEWER_ROLES = ['Lead Developer', 'Developer'];

    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('page')->index();
            // Cookie-based UUID identifying the browser, regardless of login state.
            $table->string('visitor_id')->index();
            // The VATSIM CID (users.id) when the visitor was logged in, otherwise null.
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['page', 'created_at']);
        });

        Permission::firstOrCreate(['name' => 'view page access']);

        foreach (self::VIEWER_ROLES as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo('view page access');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');

        Permission::where('name', 'view page access')->first()?->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
