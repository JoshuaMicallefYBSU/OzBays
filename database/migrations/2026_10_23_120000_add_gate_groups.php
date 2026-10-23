<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Gate groups - some airports (e.g. YBSU) report a terminal gate ("2") that walks out to several stands.
     * bays.gate lists the gate(s) a stand is reached from ("1" or "1,2"), and flight_live_bay.match records whether
     * the IRL gate matched a single bay ('bay') or a group of stands ('gate', scheduled_bay is then null).
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bays', function (Blueprint $table) {
            $table->string('gate')->nullable()->after('terminal');
        });

        Schema::table('flight_live_bay', function (Blueprint $table) {
            $table->string('match')->default('bay')->after('gate');
        });

        // AirLabs gives no terminal for single-terminal airports (e.g. YBSU) - an unmatched gate there crashed LiveBaysJob
        Schema::table('flight_live_missing_bays', function (Blueprint $table) {
            $table->string('terminal')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bays', function (Blueprint $table) {
            $table->dropColumn('gate');
        });

        Schema::table('flight_live_bay', function (Blueprint $table) {
            $table->dropColumn('match');
        });
    }
};
