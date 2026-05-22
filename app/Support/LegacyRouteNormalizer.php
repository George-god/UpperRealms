<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Normalizes /game/{classic|pages|controllers|admin}/{page} requests for Laravel routing.
 */
final class LegacyRouteNormalizer
{
    public const PREFIXES = ['classic', 'pages', 'controllers', 'admin'];

    /**
     * @return array{prefix: string, page: string, script: string, had_php_suffix: bool}|null
     */
    public static function parseGamePath(string $path): ?array
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        foreach (self::PREFIXES as $prefix) {
            $needle = 'game/'.$prefix.'/';
            if (! str_starts_with($path, $needle)) {
                continue;
            }

            $segment = substr($path, strlen($needle));
            if ($segment === '' || str_contains($segment, '/')) {
                return null;
            }

            $page = self::normalizePageName($segment);
            if ($page === null) {
                return null;
            }

            $hadPhp = str_ends_with(strtolower($segment), '.php');

            return [
                'prefix' => $prefix,
                'page' => $page,
                'script' => $page.'.php',
                'had_php_suffix' => $hadPhp,
            ];
        }

        return null;
    }

    public static function normalizePageName(string $segment): ?string
    {
        $segment = strtolower(trim($segment));
        if ($segment === '' || str_contains($segment, '..')) {
            return null;
        }

        if (str_ends_with($segment, '.php')) {
            $segment = substr($segment, 0, -4);
        }

        if ($segment === '' || ! preg_match('/^[a-z0-9_]+$/', $segment)) {
            return null;
        }

        return $segment;
    }

    /**
     * Rewrite REQUEST_URI to an extensionless path so the router receives a clean {page} segment.
     * Preserves the query string. Does not emit redirects (avoids loops).
     *
     * @return array{prefix: string, page: string, script: string}|null when rewritten or already clean
     */
    public static function requestPath(Request $request): string
    {
        $uri = (string) $request->server->get('REQUEST_URI', '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return trim((string) $path, '/');
    }

    /**
     * Laravel caches pathInfo on first access; clear after mutating server URI.
     */
    public static function resetRequestPathCache(Request $request): void
    {
        foreach (['pathInfo', 'requestUri', 'basePath', 'baseUrl'] as $property) {
            if (! property_exists($request, $property)) {
                continue;
            }

            $ref = new \ReflectionProperty($request, $property);
            $ref->setValue($request, null);
        }
    }

    public static function rewriteRequestUri(Request $request): ?array
    {
        $parsed = self::parseGamePath(self::requestPath($request));
        if ($parsed === null) {
            return null;
        }

        $request->attributes->set('legacy_route_prefix', $parsed['prefix']);
        $request->attributes->set('legacy_page', $parsed['page']);
        $request->attributes->set('legacy_script', $parsed['script']);

        $canonicalPath = '/game/'.$parsed['prefix'].'/'.$parsed['page'];
        $currentPath = self::requestPath($request);
        $canonicalTrimmed = 'game/'.$parsed['prefix'].'/'.$parsed['page'];
        $needsRewrite = $parsed['had_php_suffix'] || $currentPath !== $canonicalTrimmed;

        if ($needsRewrite) {
            $query = $request->getQueryString();
            $uri = $canonicalPath.($query !== null && $query !== '' ? '?'.$query : '');

            $request->server->set('REQUEST_URI', $uri);
            $request->server->set('PATH_INFO', $canonicalPath);
            self::resetRequestPathCache($request);
        }

        return [
            'prefix' => $parsed['prefix'],
            'page' => $parsed['page'],
            'script' => $parsed['script'],
        ];
    }
}
