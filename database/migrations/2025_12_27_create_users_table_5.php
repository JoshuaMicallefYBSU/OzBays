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
        Schema::create('users', function (Blueprint $table) {
            $table->unsignedInteger('id')->unique();
            $table->string('fname')->default(null);
            $table->string('lname')->default(null);
            $table->string('email')->default(null);
            $table->integer('gdpr_subscribed_emails')->default(0);
            $table->boolean('deleted')->default(false);
            $table->integer('init')->default(0);
            $table->boolean('display_cid_only')->default(false);
            $table->string('discord_user_id')->nullable();
            $table->string('discord_username')->nullable();
            $table->boolean('discord_member')->default(false);
            $table->string('discord_avatar')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
