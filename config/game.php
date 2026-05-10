<?php

declare(strict_types=1);

return [
    /**
     * Native PHP session name used when running legacy pages through /game/classic/{page}.
     * Separate from Laravel's session cookie so legacy $_SESSION works reliably.
     */
    'legacy_session_name' => env('LEGACY_SESSION_NAME', 'upperrealms_legacy'),

    /*
     * Legacy is integrated under the same app:
     * - Pages:   /game/classic/{script}.php  and  /game/pages/{script}.php  (alias for admin “back to game” links)
     * - Actions: /game/controllers/{script}.php  (fetch targets, forms)
     * - Admin:   /game/admin/{script}.php
     * - Static:  /game/assets/{path}  (legacy/assets),  /game/*.js  (cultivation, inventory, explore_encounter, pve)
     * Cron: GET /game/controllers/season_process.php?key=…  uses SEASON_CRON_KEY from .env (no Laravel login).
     */
];
