<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Codex\CodexCatalogSyncService;
use Illuminate\Console\Command;

class SyncCodexCatalogCommand extends Command
{
    protected $signature = 'codex:sync-catalog';

    protected $description = 'Rebuild codex_entries from game catalog tables';

    public function handle(CodexCatalogSyncService $sync): int
    {
        $count = $sync->sync();
        $this->info("Synced {$count} codex catalog rows.");

        return self::SUCCESS;
    }
}
