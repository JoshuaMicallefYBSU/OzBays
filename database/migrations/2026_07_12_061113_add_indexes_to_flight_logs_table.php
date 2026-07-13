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
        Schema::table('flight_logs', function (Blueprint $table) {
            $table->index('arrival');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flight_logs', function (Blueprint $table) {
            $table->dropIndex(['arrival']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
        });
    }
};
