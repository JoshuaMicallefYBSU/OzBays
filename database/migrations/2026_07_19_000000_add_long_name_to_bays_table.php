<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bays', function (Blueprint $table) {
            $table->string('long_name')->nullable()->after('bay');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bays', function (Blueprint $table) {
            $table->dropColumn('long_name');
        });
    }
};
