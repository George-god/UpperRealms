<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Realm;
use App\Models\User;

/**
 * Consistent player-facing realm labels (name, not numeric id).
 */
final class RealmDisplay
{
    /** @var array<int, string> */
    private static array $labelById = [];

    public static function label(?string $realmName, ?int $realmId = null): string
    {
        $name = trim((string) ($realmName ?? ''));
        if ($name !== '') {
            return __('Realm').' — '.$name;
        }

        if ($realmId !== null && $realmId > 0) {
            return __('Realm').' #'.$realmId;
        }

        return __('Realm');
    }

    public static function labelForId(int $realmId): string
    {
        if ($realmId < 1) {
            return self::label(null, null);
        }

        if (isset(self::$labelById[$realmId])) {
            return self::$labelById[$realmId];
        }

        $name = Realm::query()->whereKey($realmId)->value('name');

        return self::$labelById[$realmId] = self::label(is_string($name) ? $name : null, $realmId);
    }

    public static function forUser(User $user): string
    {
        $realmId = (int) $user->realm_id;
        if ($user->relationLoaded('realm') && $user->realm) {
            return self::label($user->realm->name, $realmId);
        }

        return self::labelForId($realmId);
    }
}
