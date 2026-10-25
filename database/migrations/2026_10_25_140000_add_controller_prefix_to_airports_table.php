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
            // VATSIM ATC facility code for this airport's terminal area (e.g. "ML" for Melbourne,
            // "SY" for Sydney) - distinct from the ICAO, used to match controller callsigns like
            // "ML-S_APP" or "SY_GND" against the airport they actually serve.
            $table->string('controller_prefix')->nullable();
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
            $table->dropColumn(['controller_prefix']);
        });
    }
};
