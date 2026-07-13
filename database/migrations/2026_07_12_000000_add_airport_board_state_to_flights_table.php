<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->timestamp('filed_etd')->nullable()->after('eibt');
            $table->timestamp('landed_at')->nullable()->after('filed_etd');
            $table->timestamp('departed_at')->nullable()->after('landed_at');
            $table->timestamp('arrived_at')->nullable()->after('departed_at');
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->dropColumn(['filed_etd', 'landed_at', 'departed_at', 'arrived_at']);
        });
    }
};
