<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'onboarding_step')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedTinyInteger('onboarding_step')->default(0);
                $table->timestamp('onboarding_completed_at')->nullable();
            });
        }

        // Existing accounts: treat as already oriented (new users keep defaults 0 / null).
        DB::table('users')->whereNull('onboarding_completed_at')->where('onboarding_step', 0)->update([
            'onboarding_step' => 1,
            'onboarding_completed_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $drop = array_values(array_filter([
            Schema::hasColumn('users', 'onboarding_step') ? 'onboarding_step' : null,
            Schema::hasColumn('users', 'onboarding_completed_at') ? 'onboarding_completed_at' : null,
        ]));
        if ($drop === []) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($drop) {
            $table->dropColumn($drop);
        });
    }
};
