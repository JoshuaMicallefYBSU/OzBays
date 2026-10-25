<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    // Every event in the bay assignment pipeline (candidates considered, live bay
    // matches, assignments, reassignments, diversions, Hoppie messages, errors...)
    // gets a row here, independent of the Flights table's own lifecycle, so it
    // survives long after the flight itself has gone offline and been deleted.
    private const VIEWER_ROLES = ['Lead Developer', 'Developer'];

    public function up(): void
    {
        Schema::create('assignment_logs', function (Blueprint $table) {
            $table->id();
            $table->string('airport')->index();
            $table->string('callsign')->index();
            $table->unsignedBigInteger('cid')->nullable();
            $table->string('event_type');
            $table->string('status')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['airport', 'callsign']);
            $table->index(['airport', 'created_at']);
        });

        Permission::firstOrCreate(['name' => 'view assignment logs']);

        foreach (self::VIEWER_ROLES as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo('view assignment logs');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_logs');

        Permission::where('name', 'view assignment logs')->first()?->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
