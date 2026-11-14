<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\LegacyPhpController;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/** Real routes/controller with a synthetic PHP page; not a real game.php rendering test. */
class LegacyPhpRouteNormalizationTest extends TestCase
{
    private ?string $fixtureRoot = null;
    private ?string $originalBasePath = null;
    private ?array $originalServer = null;
    private array $nativeSession = [];
    private bool $hadSessionGlobal = false;
    private mixed $originalSession = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue($this->app->environment('testing'));
        $this->assertSame(PHP_SESSION_NONE, session_status(), 'An unrelated native session must not be reused.');
        $this->assertSame([], $this->app->make('db')->getConnections());
        foreach (['sqlite', 'mysql', 'mariadb', 'pgsql', 'sqlsrv'] as $driver) {
            $this->app->bind('db.connector.'.$driver, static function (): never {
                throw new LogicException('Route contract tests must not open a database connection.');
            });
        }

        $this->originalBasePath = $this->app->basePath();
        $this->originalServer = $_SERVER;
        $this->hadSessionGlobal = array_key_exists('_SESSION', $GLOBALS);
        $this->originalSession = $GLOBALS['_SESSION'] ?? null;
        $this->nativeSession = [
            'name' => session_name(), 'id' => session_id(), 'path' => session_save_path(),
            'cookies' => ini_get('session.use_cookies'), 'limiter' => session_cache_limiter(),
        ];

        $root = sys_get_temp_dir().'/upperrealms_route_fixture_'.bin2hex(random_bytes(8));
        if (! mkdir($root, 0700)) {
            throw new RuntimeException('Cannot create private legacy route fixture.');
        }
        $this->fixtureRoot = $root;
        if (! mkdir($root.'/legacy/pages', 0700, true) || ! mkdir($root.'/sessions', 0700)
            || ! copy(__DIR__.'/../Fixtures/legacy-route-probe.php', $root.'/legacy/pages/game.php')) {
            throw new RuntimeException('Cannot prepare private legacy route fixture.');
        }
        session_save_path($root.'/sessions');
        session_id(bin2hex(random_bytes(16)));
        ini_set('session.use_cookies', '0');
        session_cache_limiter('');

        // Routes and PHP classes are already loaded from the real application. A private
        // base path selects the synthetic include target and is restored in tearDown.
        $this->app->setBasePath($root);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->nativeSession !== []) {
                if (session_status() === PHP_SESSION_ACTIVE && ! session_abort()) {
                    throw new RuntimeException('Cannot close the private fixture session.');
                }
                session_name($this->nativeSession['name']);
                session_id($this->nativeSession['id']);
                session_save_path($this->nativeSession['path']);
                ini_set('session.use_cookies', $this->nativeSession['cookies']);
                session_cache_limiter($this->nativeSession['limiter']);
                if ($this->hadSessionGlobal) {
                    $GLOBALS['_SESSION'] = $this->originalSession;
                } else {
                    unset($GLOBALS['_SESSION']);
                }
            }
        } finally {
            try {
                if ($this->originalServer !== null) {
                    $_SERVER = $this->originalServer;
                }
                if ($this->originalBasePath !== null) {
                    $this->app->setBasePath($this->originalBasePath);
                }
                // This root is acknowledged-owned only after mkdir succeeds; the filesystem
                // helper removes directory symlinks themselves rather than following them.
                if ($this->fixtureRoot !== null) {
                    $removed = (new Filesystem)->deleteDirectory($this->fixtureRoot);
                    clearstatcache(true, $this->fixtureRoot);
                    if (! $removed || file_exists($this->fixtureRoot) || is_link($this->fixtureRoot)) {
                        throw new RuntimeException('Private legacy route fixture cleanup failed.');
                    }
                }
            } finally {
                parent::tearDown();
            }
        }
    }

    public function test_classic_page_with_php_extension_reaches_fixture_script(): void
    {
        $this->assertFixtureDispatch('/game/classic/game.php', 'classic');
    }

    public function test_classic_page_without_php_extension_reaches_fixture_script(): void
    {
        $this->assertFixtureDispatch('/game/classic/game', 'classic');
    }

    public function test_pages_alias_accepts_php_suffix(): void
    {
        $this->assertFixtureDispatch('/game/pages/game.php', 'pages');
    }

    public function test_query_string_preserved_on_normalized_classic_url(): void
    {
        $this->assertFixtureDispatch('/game/classic/game.php?needs_login=1', 'classic', ['needs_login' => '1']);
    }

    public function test_guest_is_redirected_before_fixture_execution(): void
    {
        $this->get('/game/classic/game.php')->assertRedirect(route('login'));
    }

    public function test_nested_page_path_is_rejected(): void
    {
        $this->actingAs($this->syntheticUser())->get('/game/classic/game.php/extra')->assertNotFound();
    }

    public function test_missing_script_is_rejected(): void
    {
        $this->actingAs($this->syntheticUser())->get('/game/classic/missing.php')->assertNotFound();
    }

    public function test_non_admin_cannot_dispatch_an_admin_page(): void
    {
        $this->actingAs($this->syntheticUser())->get('/game/admin/game.php')->assertForbidden();
    }

    private function syntheticUser(): User
    {
        return (new User)->forceFill([
            'id' => 7, 'username' => 'route-fixture-user', 'is_admin' => false, 'admin_level' => 0,
            'realm_id' => 1, 'level' => 1, 'onboarding_step' => 99, 'onboarding_completed_at' => now(),
        ]);
    }

    private function assertFixtureDispatch(string $url, string $prefix, array $query = []): void
    {
        $path = '/game/'.$prefix.'/game';
        $queryString = http_build_query($query);
        $this->actingAs($this->syntheticUser())->get($url)->assertOk()->assertExactJson([
            'fixture' => 'synthetic-legacy-route-probe',
            'route_action' => LegacyPhpController::class.'@pages',
            'page_parameter' => 'game', 'prefix' => $prefix,
            'legacy_page' => 'game', 'legacy_script' => 'game.php',
            'request_uri' => $path.($queryString !== '' ? '?'.$queryString : ''),
            'query' => $query,
            'server_uri' => $path.($queryString !== '' ? '?'.$queryString : ''),
            'server_query' => $queryString, 'php_self' => $path, 'script_name' => $path,
            'method' => 'GET',
            'session' => ['user_id' => 7, 'username' => 'route-fixture-user', 'is_admin' => 0,
                'admin_level' => 0, 'realm_id' => 1, 'level' => 1],
        ]);
    }
}
