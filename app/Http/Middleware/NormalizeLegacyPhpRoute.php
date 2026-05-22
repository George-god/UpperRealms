<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\LegacyRouteNormalizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before routing so /game/classic/foo.php and /game/classic/foo both match {page}=foo.
 */
class NormalizeLegacyPhpRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        LegacyRouteNormalizer::rewriteRequestUri($request);

        return $next($request);
    }
}
