<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('npcs')) {
            return;
        }

        $name = 'Corrupted Spirit Wolf';
        $exists = DB::table('npcs')->where('name', $name)->exists();
        if ($exists) {
            return;
        }

        $realmId = 1;
        if (Schema::hasTable('realms')) {
            $realmId = (int) (DB::table('realms')->orderBy('id')->value('id') ?? 1);
        }

        DB::table('npcs')->insert([
            'name' => $name,
            'realm_id' => $realmId,
            'level' => 1,
            'base_hp' => 60,
            'base_attack' => 6,
            'base_defense' => 4,
            'reward_chi' => 5,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('npcs')) {
            return;
        }

        DB::table('npcs')->where('name', 'Corrupted Spirit Wolf')->delete();
    }
};
