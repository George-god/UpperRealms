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

        $columns = [
            'strength' => fn (Blueprint $t) => $t->unsignedSmallInteger('strength')->default(5),
            'agility' => fn (Blueprint $t) => $t->unsignedSmallInteger('agility')->default(5),
            'vitality' => fn (Blueprint $t) => $t->unsignedSmallInteger('vitality')->default(5),
            'spirit' => fn (Blueprint $t) => $t->unsignedSmallInteger('spirit')->default(5),
            'soul' => fn (Blueprint $t) => $t->unsignedSmallInteger('soul')->default(5),
            'willpower' => fn (Blueprint $t) => $t->unsignedSmallInteger('willpower')->default(5),
            'attribute_points' => fn (Blueprint $t) => $t->unsignedSmallInteger('attribute_points')->default(0),
            'stat_specialization' => fn (Blueprint $t) => $t->string('stat_specialization', 32)->nullable(),
            'body_type' => fn (Blueprint $t) => $t->string('body_type', 48)->nullable(),
            'attribute_respec_count' => fn (Blueprint $t) => $t->unsignedSmallInteger('attribute_respec_count')->default(0),
        ];

        $toAdd = [];
        foreach ($columns as $name => $callback) {
            if (! Schema::hasColumn('users', $name)) {
                $toAdd[$name] = $callback;
            }
        }

        if ($toAdd !== []) {
            Schema::table('users', function (Blueprint $table) use ($toAdd) {
                foreach ($toAdd as $callback) {
                    $callback($table);
                }
            });
        }

        if (Schema::hasColumn('users', 'strength')) {
            DB::table('users')->where('strength', 0)->update([
                'strength' => 5,
                'agility' => 5,
                'vitality' => 5,
                'spirit' => 5,
                'soul' => 5,
                'willpower' => 5,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $drop = array_values(array_filter([
            Schema::hasColumn('users', 'strength') ? 'strength' : null,
            Schema::hasColumn('users', 'agility') ? 'agility' : null,
            Schema::hasColumn('users', 'vitality') ? 'vitality' : null,
            Schema::hasColumn('users', 'spirit') ? 'spirit' : null,
            Schema::hasColumn('users', 'soul') ? 'soul' : null,
            Schema::hasColumn('users', 'willpower') ? 'willpower' : null,
            Schema::hasColumn('users', 'attribute_points') ? 'attribute_points' : null,
            Schema::hasColumn('users', 'stat_specialization') ? 'stat_specialization' : null,
            Schema::hasColumn('users', 'body_type') ? 'body_type' : null,
            Schema::hasColumn('users', 'attribute_respec_count') ? 'attribute_respec_count' : null,
        ]));

        if ($drop === []) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($drop) {
            $table->dropColumn($drop);
        });
    }
};
