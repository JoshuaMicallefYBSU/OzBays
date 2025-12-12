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
        Schema::create('bay_conflict', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('callsign');
            $table->unsignedBigInteger('bay');
            $table->timestamps();


            $table->foreign('callsign')->references('id')->on('flights');
            $table->foreign('bay')->references('id')->on('bays');
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
