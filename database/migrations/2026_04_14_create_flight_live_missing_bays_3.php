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
        Schema::create('flight_live_missing_bays', function (Blueprint $table) {
            $table->id();
            $table->string('airport');
            $table->string('terminal');
            $table->string('bay');
            $table->string('callsign')->nullable();
            $table->string('aircraft')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
