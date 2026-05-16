<?php

declare(strict_types=1);

/**
 * Player-facing realm labels for legacy PHP pages (mirrors App\Support\RealmDisplay).
 */

if (! function_exists('realm_display_label')) {
    function realm_display_label(?string $realmName, ?int $realmId = null): string
    {
        $name = trim((string) ($realmName ?? ''));
        if ($name !== '') {
            return 'Realm — '.$name;
        }

        if ($realmId !== null && $realmId > 0) {
            return 'Realm #'.$realmId;
        }

        return 'Realm';
    }
}

if (! function_exists('realm_display_label_by_id')) {
    /**
     * @return array<int, string>
     */
    function &realm_display_label_cache(): array
    {
        static $cache = [];

        return $cache;
    }

    function realm_display_label_by_id(int $realmId): string
    {
        if ($realmId < 1) {
            return realm_display_label(null, null);
        }

        $cache = &realm_display_label_cache();
        if (isset($cache[$realmId])) {
            return $cache[$realmId];
        }

        try {
            $db = \Game\Config\Database::getConnection();
            $stmt = $db->prepare('SELECT name FROM realms WHERE id = ? LIMIT 1');
            $stmt->execute([$realmId]);
            $name = $stmt->fetchColumn();
            $cache[$realmId] = realm_display_label($name !== false ? (string) $name : null, $realmId);
        } catch (\Throwable $e) {
            error_log('realm_display_label_by_id: '.$e->getMessage());
            $cache[$realmId] = realm_display_label(null, $realmId);
        }

        return $cache[$realmId];
    }
}
