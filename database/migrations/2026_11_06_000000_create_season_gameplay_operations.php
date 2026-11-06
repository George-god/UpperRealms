<?php

use App\Support\GameplayOperationLedger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void { DB::statement(GameplayOperationLedger::ddl()); }

    public function down(): void
    {
        throw new RuntimeException('Replay ledger cannot be dropped: retained keys prevent duplicate gameplay.');
    }
};
