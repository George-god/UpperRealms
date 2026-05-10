<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Runs legacy PHP from legacy/pages, legacy/controllers, or legacy/admin under /game/...
 * Bridges Laravel auth into the native PHP session expected by legacy code.
 */
final class LegacyPhpController extends Controller
{
    private const PAGES_BLOCKED = [
        'login.php',
        'register.php',
        'logout.php',
    ];

    public function pages(Request $request, string $page): SymfonyResponse
    {
        return $this->run($request, 'pages', $page);
    }

    public function controllers(Request $request, string $page): SymfonyResponse
    {
        return $this->run($request, 'controllers', $page);
    }

    public function admin(Request $request, string $page): SymfonyResponse
    {
        return $this->run($request, 'admin', $page);
    }

    private function run(Request $request, string $subdir, string $page): SymfonyResponse
    {
        $script = strtolower($page);
        if (! str_ends_with($script, '.php')) {
            $script .= '.php';
        }
        if (! preg_match('/^[a-z0-9_]+\.php$/', $script)) {
            abort(404);
        }

        $base = realpath(base_path('legacy/'.$subdir));
        $path = realpath(base_path('legacy/'.$subdir.'/'.$script));
        if ($base === false || $path === false || ! str_starts_with($path, $base)) {
            abort(404);
        }

        $isSeasonCron = $subdir === 'controllers' && $script === 'season_process.php';
        if ($isSeasonCron) {
            ob_start();
            try {
                require $path;
            } finally {
                $body = ob_get_clean();
            }

            return response($body !== false ? $body : '', 200);
        }

        $user = $request->user();
        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        if ($subdir === 'pages' && in_array($script, self::PAGES_BLOCKED, true)) {
            return match ($script) {
                'login.php' => redirect()->route('login'),
                'register.php' => redirect()->route('register'),
                default => redirect()->route('game.hub'),
            };
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_name((string) config('game.legacy_session_name'));
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['is_admin'] = $user->is_admin ? 1 : 0;
        $_SESSION['admin_level'] = $user->admin_level;
        $_SESSION['realm_id'] = $user->realm_id;
        $_SESSION['level'] = $user->level;

        ob_start();
        try {
            require $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        $buffered = ob_get_clean();
        if ($buffered !== false && $buffered !== '') {
            return response($buffered, 200);
        }

        return response('', 200);
    }
}
