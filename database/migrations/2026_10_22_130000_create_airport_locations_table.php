<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Coordinates for any airport (mostly departure airports OzBays doesn't serve), looked up once from AirLabs
     * and kept here so each airport is only requested once. lat/lon stay null when the lookup found nothing.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('airport_locations', function (Blueprint $table) {
            $table->id();
            $table->string('icao', 4)->unique();
            $table->string('name')->nullable();
            $table->decimal('lat', 10, 6)->nullable();
            $table->decimal('lon', 10, 6)->nullable();
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
        Schema::dropIfExists('airport_locations');
    }
};
