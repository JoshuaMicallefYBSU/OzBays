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
            // Of the aircraft counted in stats_inbound, how many are within 200NM and already
            // have a bay assigned - stats_inbound itself (the "Tracked" figure) is unchanged.
            $table->integer('stats_inbound_bay_assigned')->nullable();

            // How stats_ground splits by why the aircraft is parked here: arrived (filed arr =
            // this airport), departing (filed dep = this airport), or no flight plan at all.
            // Kept as JSON (not separate columns) since it's always read/written as one group,
            // same reasoning as stats_controller_positions.
            $table->json('stats_ground_breakdown')->nullable();
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
            $table->dropColumn(['stats_inbound_bay_assigned', 'stats_ground_breakdown']);
        });
    }
};
