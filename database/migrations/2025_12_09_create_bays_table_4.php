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
        Schema::create('bays', function (Blueprint $table) {
            $table->id();
            $table->string('airport');
            $table->string('bay');
            $table->string('lat');
            $table->string('lon');
            $table->string('aircraft')->nullable();
            $table->integer('priority')->nullable();
            $table->string('operators')->nullable();
            $table->string('pax_type')->nullable();
            $table->integer('status')->nullable(); //null=free, 1=reserved, 2=occupied
            $table->string('callsign')->nullable();
            $table->string('clear')->nullable(); // Bay Allocation Check
            $table->string('check_exist')->nullable(); // Aerodrome Updates check
            $table->timestamps();
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
