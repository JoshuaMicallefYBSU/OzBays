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
        Schema::table('airports', function (Blueprint $table) {
            $table->boolean('live_bays')->nullable();
            $table->string('live_type')->nullable();
            $table->string('live_update_times')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('airports', function (Blueprint $table) {
            $table->dropColumn(['live_bays']);
            $table->dropColumn(['live_type']);
            $table->dropColumn(['live_update_times']);
        });
    }
};
