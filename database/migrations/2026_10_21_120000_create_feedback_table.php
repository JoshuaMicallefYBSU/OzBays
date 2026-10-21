<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    // Roles allowed to read feedback in the admin dashboard
    private const VIEWER_ROLES = ['Lead Developer', 'Developer'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->json('ratings');                 // question key => 1-10
            $table->string('flight')->nullable();    // optional callsign / airport the feedback is about
            $table->longText('body');                // sanitised HTML from the editor
            $table->timestamps();
        });

        Permission::firstOrCreate(['name' => 'view feedback']);

        foreach (self::VIEWER_ROLES as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo('view feedback');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');

        Permission::where('name', 'view feedback')->first()?->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
