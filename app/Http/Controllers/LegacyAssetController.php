<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves static files from legacy/assets for classic UI (paths like ../assets/...).
 */
final class LegacyAssetController extends Controller
{
    public function __invoke(string $path): BinaryFileResponse|Response
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $full = realpath(base_path('legacy/assets/'.$path));
        $base = realpath(base_path('legacy/assets'));
        if ($full === false || $base === false || ! str_starts_with($full, $base) || ! is_file($full)) {
            abort(404);
        }

        return response()->file($full);
    }
}
