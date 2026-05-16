<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $this->indexIfExists('pve_battles', function (Blueprint $table) {
            $table->index(['user_id', 'npc_id', 'winner'], 'idx_pve_user_npc_winner');
        });

        $this->indexIfExists('user_artifacts', function (Blueprint $table) {
            $table->index(['user_id', 'artifact_id'], 'idx_user_artifact_pair');
        });

        $this->indexIfExists('user_bloodlines', function (Blueprint $table) {
            $table->index(['user_id', 'is_active'], 'idx_user_bloodline_active');
        });

        $this->indexIfExists('user_cultivation_manuals', function (Blueprint $table) {
            $table->index(['user_id', 'manual_id'], 'idx_user_manual_pair');
        });

        $this->indexIfExists('dungeon_runs', function (Blueprint $table) {
            $table->index(['user_id', 'is_completed'], 'idx_dungeon_user_completed');
        });

        $this->indexIfExists('dao_records', function (Blueprint $table) {
            $table->index(['user_id', 'event_type', 'created_at'], 'idx_dao_user_event_time');
        });

        $this->indexIfExists('sect_members', function (Blueprint $table) {
            $table->index(['sect_id', 'user_id'], 'idx_sect_member_pair');
        });

        $this->indexIfExists('inventory', function (Blueprint $table) {
            $table->index(['user_id', 'is_equipped'], 'idx_inventory_user_equipped');
        });

        $this->indexIfExists('user_codex_entries', function (Blueprint $table) {
            $table->index(['user_id', 'is_new'], 'idx_codex_user_new');
        });
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        foreach ([
            'pve_battles' => 'idx_pve_user_npc_winner',
            'user_artifacts' => 'idx_user_artifact_pair',
            'user_bloodlines' => 'idx_user_bloodline_active',
            'user_cultivation_manuals' => 'idx_user_manual_pair',
            'dungeon_runs' => 'idx_dungeon_user_completed',
            'dao_records' => 'idx_dao_user_event_time',
            'sect_members' => 'idx_sect_member_pair',
            'inventory' => 'idx_inventory_user_equipped',
            'user_codex_entries' => 'idx_codex_user_new',
        ] as $table => $index) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropIndex($index);
                });
            } catch (\Throwable) {
                //
            }
        }
    }

    private function indexIfExists(string $table, callable $callback): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, $callback);
        } catch (\Throwable) {
            // Index may already exist on imported schemas.
        }
    }
};
