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
        Schema::create('bay_allocation', function (Blueprint $table) {
            $table->id();
            $table->string('airport',4);
            $table->unsignedBigInteger('bay');
            $table->string('bay_core');
            $table->unsignedBigInteger('callsign');
            $table->string('status',10);
            $table->datetime('eibt');
            $table->datetime('eobt');
            $table->timestamps();

            $table->foreign('bay')->references('id')->on('bays');
            $table->foreign('callsign')->references('id')->on('flights');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bays');
    }
};
