<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SeasonService;
use Illuminate\Console\Command;
use Throwable;

final class ProcessSeasonsCommand extends Command
{
    protected $signature = 'game:process-seasons';

    protected $description = 'Finalize expired seasons and ensure an active season exists';

    public function handle(SeasonService $seasons): int
    {
        try {
            $result = $seasons->processEndedSeasons();
        } catch (Throwable) {
            $this->error('Season processing failed.');

            return self::FAILURE;
        }

        if (! $result['ok']) {
            // Service errors can contain database connection details; never print them.
            $this->error('Season processing failed.');

            return self::FAILURE;
        }

        $this->info(sprintf('Processed %d expired season(s).', $result['processed']));

        return self::SUCCESS;
    }
}
