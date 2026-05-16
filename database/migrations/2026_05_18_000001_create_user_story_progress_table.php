<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_story_progress')) {
            return;
        }

        Schema::create('user_story_progress', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->string('current_step', 64)->default('awakening');
            $table->json('completed_steps')->nullable();
            $table->json('flags')->nullable();
            $table->timestamp('intro_completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_story_progress');
    }
};
