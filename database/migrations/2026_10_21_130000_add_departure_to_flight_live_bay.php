<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The real-world departure airport, so an IRL bay is only used when the network flight flew the same route
     * (e.g. network QFA204 ML-SY must not take the IRL international gate of QFA204 from overseas).
     *
     * @return void
     */
    public function up()
    {
        Schema::table('flight_live_bay', function (Blueprint $table) {
            $table->string('departure', 4)->after('airport')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('flight_live_bay', function (Blueprint $table) {
            $table->dropColumn(['departure']);
        });
    }
};
