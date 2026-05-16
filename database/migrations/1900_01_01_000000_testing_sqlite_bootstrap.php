<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal schema so PHPUnit (sqlite) can run auth/feature tests without the full MySQL import.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('realms', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 50);
        });

        DB::table('realms')->insert([
            'id' => 1,
            'name' => 'Qi Refining',
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 30)->unique();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->rememberToken();
            $table->boolean('is_admin')->default(false);
            $table->unsignedInteger('realm_id')->default(1);
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('chi')->default(100);
            $table->unsignedBigInteger('max_chi')->default(100);
            $table->unsignedInteger('attack')->default(10);
            $table->unsignedInteger('defense')->default(10);
            $table->unsignedSmallInteger('strength')->default(5);
            $table->unsignedSmallInteger('agility')->default(5);
            $table->unsignedSmallInteger('vitality')->default(5);
            $table->unsignedSmallInteger('spirit')->default(5);
            $table->unsignedSmallInteger('soul')->default(5);
            $table->unsignedSmallInteger('willpower')->default(5);
            $table->unsignedSmallInteger('attribute_points')->default(0);
            $table->string('stat_specialization', 32)->nullable();
            $table->string('body_type', 48)->nullable();
            $table->unsignedSmallInteger('attribute_respec_count')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->decimal('rating', 8, 2)->default(1000);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! app()->environment('testing')) {
            return;
        }
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('realms');
    }
};
