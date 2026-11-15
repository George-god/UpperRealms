# Legacy route tests: dispatch versus game-page rendering

The four original `LegacyPhpRouteNormalizationTest` cases mixed two contracts: selecting a legacy script through Laravel and rendering the real database-backed `legacy/pages/game.php`. They created a Laravel SQLite user, then included a page that separately opens the legacy MySQL singleton. All four failed at `legacy/config/database.php:101` on the SQLite-only local PHP build because `PDO::MYSQL_ATTR_INIT_COMMAND` was unavailable. Merely installing PDO MySQL would not supply the required game tables or isolate that connection.

The two test layers are deliberately separate. Passing the dispatch layer does **not** establish that the real game page renders successfully. No MariaDB integration test is added, executed or claimed by this correction.

## Layer A: database-independent contracts

`tests/Unit/LegacyRouteNormalizerTest.php` keeps the parsing/name-validation contract: supported prefixes, removal of a PHP suffix, invalid page rejection and query-preserving URI rewriting.

`tests/Feature/LegacyPhpRouteNormalizationTest.php` uses the **real** middleware, production route definitions, final controller, authentication checks, filesystem path checks and PHP-session/server-global bridge. The controller is not mocked and no replacement route is registered. After the application has booted, each case temporarily points its base path at a newly created private directory containing a copy of `tests/Fixtures/legacy-route-probe.php` as `legacy/pages/game.php`. The probe emits JSON describing what was actually dispatched and bridged. It never includes the legacy bootstrap, PDO or gameplay services.

| Original URL scenario | Assertions retained and strengthened |
| --- | --- |
| `/game/classic/game.php` | HTTP 200 from the included probe, real controller action and `game` route parameter, `game.php` script, normalized classic URI and bridged identity |
| `/game/classic/game` | The same actual include and identity contract without a suffix |
| `/game/pages/game.php` | Actual dispatch through the pages alias, with the alias retained in the normalized URI and native script globals |
| `/game/classic/game.php?needs_login=1` | Actual dispatch plus the query key/value in the Laravel request, canonical request URI, native request URI and native query string |

The first two method names now explicitly say `reaches_fixture_script`. Every positive case still asserts HTTP 200 and additionally compares the entire probe response. This detects incorrect controller selection, parameters, script attributes, prefix, query propagation or session identity; it does not merely check that a route exists.

Four additional negative cases verify guest redirection, rejection of a nested page path, rejection of a missing script, and denial of an admin route to a non-admin. They exercise the real route/controller/middleware checks without including an actual gameplay file. They are not a complete traversal, cookie-authentication or authorization audit.

### Isolation and cleanup

- No `RefreshDatabase`, factories that persist a user, or migration commands are used by this class. The authenticated principal is an unsaved synthetic `User`.
- The test requires no pre-existing Laravel connections and binds database connectors to fail if a request attempts to open one.
- The only include target exists inside the invocation-owned temporary tree, not the repository's `legacy` directory.
- Native PHP sessions use a private temporary directory and synthetic session ID. Cookies are disabled only for this test fixture; cookie/session persistence across real browser requests remains integration coverage.
- The original application base path, server globals, native-session name/ID/path/settings and session global are restored. Private-session abortion and temporary-tree deletion occur during teardown, which always delegates to Laravel cleanup. Deletion checks that the owned root actually disappeared, rather than trusting the filesystem helper's return value alone.
- Existing opt-in MariaDB tests and their skip requirements are unchanged. Nothing is silently skipped to make these dispatch tests pass.

Run the dispatch and parsing contracts from an isolated checkout with the standard testing SQLite settings:

```bash
php vendor/bin/phpunit --do-not-cache-result tests/Feature/LegacyPhpRouteNormalizationTest.php
php vendor/bin/phpunit --do-not-cache-result tests/Unit/LegacyRouteNormalizerTest.php
```

The guarded Windows runner additionally rejects active environment files/cached configuration, requires SQLite as the only PDO driver, and keeps framework runtime files outside Git. It preloads the same locked vendor dependencies with application/test namespaces pinned to the selected checkout. Its external PHPUnit configuration preserves the normal suites with no exclusions.

## Layer B: required real-page integration work, not implemented

Before claiming real `game.php` reachability, a separately approved integration task must:

1. Use an explicitly opted-in disposable MariaDB instance on a pinned loopback endpoint, and verify its independently provisioned token **before any schema mutation**. Missing/mismatched tokens must fail closed, with negative regression tests.
2. Create an invocation-owned random schema and load only the necessary authoritative fixture definitions and synthetic data. Configure both Laravel and the legacy singleton to that exact schema, with no fallback to default game credentials, `.env` or a persistent schema.
3. Supply the actual dependencies of `game.php`, including its realm/stat/event/artifact helpers. A game-page GET can attempt writes such as the daily artifact roll; this is not a read-only database smoke test.
4. Render the real page through the production controller for the same four URL scenarios. Assert expected game content and synthetic player identity, actual query behavior where applicable, and absence of SQL diagnostics. Do not use the dispatch probe for this layer.
5. Check native cookie/session persistence in a suitably isolated HTTP environment if browser behavior is part of the claim. The database-independent probe intentionally disables native cookies.
6. Bound connection/query/process execution and revalidate server identity plus schema ownership before cleanup. Surface cleanup failures and inventory any leftovers; never delete a schema based only on its name.

The older `DisposableSeasonDatabase` on this baseline does not independently verify a database identity token before its `CREATE DATABASE`. Do **not** opt it in for new page-rendering tests as-is. Separately review/backport the hardened test-only ownership/identity infrastructure before building Layer B; its presence on Slice 1 does not authorize modifying or depending on that feature branch here.

## CI compatibility

The existing GitHub workflow declares PHP 8.2/8.3/8.4, SQLite/PDO SQLite and the normal Laravel extensions; no MariaDB service is configured. The corrected layer needs standard PHP JSON/session/filesystem support and a writable private temporary directory, plus the existing Laravel test dependencies. It does not need PDO MySQL or a database server. No CI configuration change is necessary for Layer A.

This is a source/configuration compatibility assessment, not an observed GitHub Actions pass. Actual CI execution remains pending. Layer B still requires separate verified disposable MariaDB infrastructure and authorization; installing the MySQL extension alone is insufficient.
