<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Up to 3 bays a pilot has asked for on their dashboard, in order of preference (position 1 = first choice).
    // Tied to the user, callsign and arrival airport so a refile or new flight never picks up old requests.
    public function up(): void
    {
        Schema::create('bay_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('callsign');
            $table->string('airport', 4);
            $table->foreignId('bay_id')->constrained('bays')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->index(['user_id', 'callsign', 'airport']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bay_requests');
    }
};
