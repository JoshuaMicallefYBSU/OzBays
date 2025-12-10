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
        Schema::table('flights', function (Blueprint $table) {
            $table->unsignedBigInteger('current_bay')->nullable();
            $table->unsignedBigInteger('scheduled_bay')->nullable();

            $table->foreign('current_bay')->references('id')->on('bays');
            $table->foreign('scheduled_bay')->references('id')->on('bays');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->dropColumn(['current_bay']);
            $table->dropColumn(['scheduled_bay']);
        });
    }
};
