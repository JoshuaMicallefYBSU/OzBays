<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('airports', function (Blueprint $table) {
            // Per-position online/offline breakdown behind the stats_controllers count
            // (DEL/GND/TWR/APP_DEP => bool) - kept as a separate JSON column rather than
            // replacing stats_controllers itself, so the existing integer sort/filter on
            // stats_controllers (top-3 airports by controller count) keeps working.
            $table->json('stats_controller_positions')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('airports', function (Blueprint $table) {
            $table->dropColumn(['stats_controller_positions']);
        });
    }
};
