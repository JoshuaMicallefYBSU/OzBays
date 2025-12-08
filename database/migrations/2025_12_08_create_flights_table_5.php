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
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->string('callsign');
            $table->string('dep');
            $table->string('arr');
            $table->string('ac');
            $table->string('type');
            $table->string('lat');
            $table->string('lon');
            $table->string('speed');
            $table->string('distance');
            $table->datetime('elt')->nullable();
            $table->datetime('eibt')->nullable();
            $table->string('status')->nullable();
            $table->integer('online')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
