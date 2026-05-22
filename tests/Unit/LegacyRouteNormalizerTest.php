<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LegacyRouteNormalizer;
use Illuminate\Http\Request;
use Tests\TestCase;

class LegacyRouteNormalizerTest extends TestCase
{
    public function test_normalize_page_name_strips_php_extension(): void
    {
        $this->assertSame('game', LegacyRouteNormalizer::normalizePageName('game.php'));
        $this->assertSame('game', LegacyRouteNormalizer::normalizePageName('game'));
        $this->assertNull(LegacyRouteNormalizer::normalizePageName('../game.php'));
    }

    public function test_parse_game_path_for_all_prefixes(): void
    {
        $this->assertSame('world_map', LegacyRouteNormalizer::parseGamePath('game/classic/world_map.php')['page']);
        $this->assertSame('pages', LegacyRouteNormalizer::parseGamePath('game/pages/inventory.php')['prefix']);
        $this->assertSame('explore_region', LegacyRouteNormalizer::parseGamePath('game/controllers/explore_region.php')['page']);
        $this->assertSame('admin', LegacyRouteNormalizer::parseGamePath('game/admin/users.php')['prefix']);
    }

    public function test_rewrite_request_uri_strips_php_and_preserves_query(): void
    {
        $request = Request::create('/game/classic/game.php?tab=chi', 'GET');
        LegacyRouteNormalizer::rewriteRequestUri($request);

        $this->assertSame('/game/classic/game?tab=chi', $request->server->get('REQUEST_URI'));
        $this->assertSame('game', $request->attributes->get('legacy_page'));
        $this->assertSame('game.php', $request->attributes->get('legacy_script'));
    }

    public function test_rewrite_skipped_when_path_already_extensionless(): void
    {
        $request = Request::create('/game/classic/game', 'GET');
        $request->server->set('REQUEST_URI', '/game/classic/game');
        LegacyRouteNormalizer::rewriteRequestUri($request);

        $this->assertSame('/game/classic/game', $request->server->get('REQUEST_URI'));
    }
}
