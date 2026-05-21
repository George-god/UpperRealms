<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Exposes the underlying PDO used by Laravel for legacy service code paths.
 */
final class PdoDatabase
{
    public static function connection(): PDO
    {
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}
