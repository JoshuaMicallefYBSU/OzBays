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
            $table->string('dep')->nullable();
            $table->string('arr')->nullable();
            $table->string('ac')->nullable();
            $table->string('hdg');
            $table->string('type')->nullable();
            $table->string('lat');
            $table->string('lon');
            $table->string('speed');
            $table->string('alt');
            $table->integer('distance')->nullable();
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
