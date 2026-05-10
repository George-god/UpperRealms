<?php

declare(strict_types=1);

use App\Support\MySqlStatementSplitter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $path = base_path('legacy/database_full.sql');
        if (! is_file($path)) {
            throw new RuntimeException('Missing legacy/database_full.sql — restore from repo history if needed.');
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Could not read legacy/database_full.sql');
        }

        $sql = preg_replace('/^\s*CREATE DATABASE IF NOT EXISTS[^;]+;/im', '', $sql) ?? $sql;
        $sql = preg_replace('/^\s*USE\s+[^;]+;/im', '', $sql) ?? $sql;

        $driver = DB::getDriverName();
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            throw new RuntimeException('Game schema import requires mysql/mariadb (got '.$driver.').');
        }

        foreach (MySqlStatementSplitter::split($sql) as $statement) {
            DB::statement($statement);
        }
    }

    public function down(): void
    {
        // Non-reversible full schema import; use db:wipe in dev only.
    }
};
