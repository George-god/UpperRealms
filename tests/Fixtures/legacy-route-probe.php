<?php

declare(strict_types=1);

/** Synthetic include target for the real controller. No legacy bootstrap, PDO or game services. */
echo json_encode([
    'fixture' => 'synthetic-legacy-route-probe',
    'route_action' => $request->route()->getActionName(),
    'page_parameter' => $request->route('page'),
    'prefix' => $request->attributes->get('legacy_route_prefix'),
    'legacy_page' => $request->attributes->get('legacy_page'),
    'legacy_script' => $request->attributes->get('legacy_script'),
    'request_uri' => $request->getRequestUri(), 'query' => $request->query(),
    'server_uri' => $_SERVER['REQUEST_URI'], 'server_query' => $_SERVER['QUERY_STRING'],
    'php_self' => $_SERVER['PHP_SELF'], 'script_name' => $_SERVER['SCRIPT_NAME'],
    'method' => $_SERVER['REQUEST_METHOD'],
    'session' => array_intersect_key($_SESSION, array_flip([
        'user_id', 'username', 'is_admin', 'admin_level', 'realm_id', 'level',
    ])),
], JSON_THROW_ON_ERROR);
