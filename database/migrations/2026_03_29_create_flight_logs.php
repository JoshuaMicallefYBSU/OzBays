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
        Schema::create('flight_logs', function (Blueprint $table) {
            $table->id();
            $table->string('callsign');
            $table->string('airline')->nullable();
            $table->string('arrival');
            $table->string('type');
            $table->string('aircraft');
            $table->integer('user_id');
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
        Schema::table('flight_logs', function (Blueprint $table) {
            $table->dropColumn(['callsign']);
            $table->dropColumn(['airline']);
            $table->dropColumn(['arrival']);
            $table->dropColumn(['type']);
            $table->dropColumn(['aircraft']);
            $table->dropColumn(['user_id']);
        });
    }
};
