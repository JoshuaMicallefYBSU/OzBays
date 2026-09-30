<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    // Roles allowed to review contributor applications
    private const REVIEWER_ROLES = ['Lead Developer', 'Maintainer'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contributor_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('description');
            $table->text('experience')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Permission::firstOrCreate(['name' => 'review applications']);

        foreach (self::REVIEWER_ROLES as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo('review applications');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contributor_applications');

        Permission::where('name', 'review applications')->first()?->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
