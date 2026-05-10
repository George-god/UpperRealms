<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Facade for Dao paths, techniques, petitions, and admin commands.
 */
final class DaoService
{
    public function __construct(
        public readonly DaoPathService $paths,
        public readonly DaoTechniqueService $techniques,
        public readonly DaoPetitionService $petitions,
        public readonly DaoCommandService $commands,
    ) {}
}
