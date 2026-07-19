<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_change_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('target', 20)->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('submitted_by');
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'target']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_change_requests');
    }
};
