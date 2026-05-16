<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('codex_entries')) {
            return;
        }

        Schema::create('codex_entries', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32);
            $table->string('entry_key', 120)->unique();
            $table->string('title', 160);
            $table->string('teaser', 500)->nullable();
            $table->text('body')->nullable();
            $table->string('icon', 16)->nullable();
            $table->string('source_type', 48)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_ref', 120)->nullable();
            $table->string('unlock_hint', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['category', 'sort_order']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('user_codex_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->foreignId('codex_entry_id')->constrained('codex_entries')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamp('unlocked_at')->useCurrent();
            $table->timestamp('first_seen_at')->nullable();
            $table->boolean('is_new')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'codex_entry_id']);
            $table->index(['user_id', 'is_new']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('user_codex_entries');
        Schema::dropIfExists('codex_entries');
    }
};
