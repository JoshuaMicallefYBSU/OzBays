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
            $table->string('type')->nullable();
            $table->integer('status')->nullable(); //null=free, 1=reserved, 2=occupied
            $table->string('assign_ac')->nullable();
            $table->datetime('booking1_start')->nullable();
            $table->datetime('booking1_end')->nullable();
            $table->datetime('booking2_start')->nullable();
            $table->datetime('booking2_end')->nullable();
            $table->string('check_exist')->nullable();
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
