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
        Schema::create('task_logs_fallback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->string('action');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('data')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id')->nullable();
            $table->string('method')->nullable();
            $table->text('url')->nullable();
            $table->text('original_error')->nullable(); // Error that caused fallback
            $table->timestamps();

            // Indexes for performance
            $table->index('task_id');
            $table->index('action');
            $table->index('created_at');
            $table->index(['task_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_logs_fallback');
    }
};