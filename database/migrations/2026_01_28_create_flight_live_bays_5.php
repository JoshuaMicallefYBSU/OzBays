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
        Schema::create('flight_live_bay', function (Blueprint $table) {
            $table->id();
            $table->string('callsign');
            $table->string('airport');
            $table->string('terminal');
            $table->string('gate');
            $table->unsignedBigInteger('scheduled_bay')->nullable();
            $table->timestamps();

            $table->foreign('scheduled_bay')->references('id')->on('bays');
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
            $table->dropColumn(['callsign']);
            $table->dropColumn(['airport']);
            $table->dropColumn(['terminal']);
            $table->dropColumn(['gate']);
            $table->dropColumn(['scheduled_bay']);
        });
    }
};
