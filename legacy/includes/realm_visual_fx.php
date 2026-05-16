<?php

declare(strict_types=1);

/**
 * Ambient realm FX markup for legacy PHP pages — mirrors App\View\Components\RealmVisualFx.
 */
if (!function_exists('realm_visual_fx_tier')) {
    function realm_visual_fx_tier(int $realmId): string
    {
        $id = max(1, $realmId);

        return match (true) {
            $id <= 1 => 'qi',
            $id === 2 => 'foundation',
            $id === 3 => 'core',
            $id === 4 => 'nascent',
            $id === 5 => 'transformation',
            default => 'immortal',
        };
    }

    function realm_visual_fx_dao_class(?string $raw): string
    {
        $raw = strtolower((string) ($raw ?? ''));
        $raw = preg_replace('/[^a-z0-9_-]+/', '', $raw) ?? '';

        return match ($raw) {
            'flame', 'fire', 'inferno' => 'fire',
            'water', 'tide', 'flow' => 'water',
            'wood', 'life', 'nature' => 'wood',
            'metal', 'gold', 'blade' => 'metal',
            'earth', 'stone' => 'earth',
            'ice', 'frost' => 'ice',
            'demonic', 'demon', 'devil', 'unorthodox' => 'demonic',
            'righteous', 'orthodox', 'light' => 'righteous',
            default => $raw !== '' ? 'neutral' : '',
        };
    }

    function realm_visual_fx_blood_class(?string $raw): string
    {
        $raw = strtolower((string) ($raw ?? ''));
        $raw = preg_replace('/[^a-z0-9_-]+/', '', $raw) ?? '';

        return match ($raw) {
            'dragon', 'drake', 'wyrm' => 'dragon',
            'phoenix', 'vermilion' => 'phoenix',
            'demon', 'infernal' => 'demon',
            'celestial', 'immortal' => 'celestial',
            'beast', 'feral' => 'beast',
            default => $raw !== '' ? 'generic' : '',
        };
    }

    function realm_visual_fx_render_overlay(int $realmId, ?string $daoFlair, ?string $bloodlineFlair): void
    {
        $tier = realm_visual_fx_tier($realmId);
        $fxDao = realm_visual_fx_dao_class($daoFlair);
        $fxBlood = realm_visual_fx_blood_class($bloodlineFlair);

        $classes = 'realm-fx-root realm-fx--'.$tier;
        if ($fxDao !== '') {
            $classes .= ' realm-fx--dao-'.$fxDao;
        }
        if ($fxBlood !== '') {
            $classes .= ' realm-fx--blood-'.$fxBlood;
        }

        echo '<div class="'.htmlspecialchars($classes, ENT_QUOTES, 'UTF-8').'" aria-hidden="true" data-realm-fx-root data-realm-fx-tier="'.htmlspecialchars($tier, ENT_QUOTES, 'UTF-8').'" data-realm-fx-realm-id="'.(int) $realmId.'"';
        if ($fxDao !== '') {
            echo ' data-realm-fx-dao="'.htmlspecialchars($fxDao, ENT_QUOTES, 'UTF-8').'"';
        }
        if ($fxBlood !== '') {
            echo ' data-realm-fx-bloodline="'.htmlspecialchars($fxBlood, ENT_QUOTES, 'UTF-8').'"';
        }
        echo '>';
        echo '<div class="realm-fx-layer realm-fx-ambient"></div>';
        echo '<div class="realm-fx-layer realm-fx-glow"></div>';
        echo '<div class="realm-fx-layer realm-fx-auras"></div>';
        echo '<div class="realm-fx-layer realm-fx-rings" data-realm-fx-rings></div>';
        echo '<div class="realm-fx-layer realm-fx-ember"></div>';
        echo '<div class="realm-fx-layer realm-fx-lightning"></div>';
        echo '<div class="realm-fx-layer realm-fx-distort"></div>';
        echo '<div class="realm-fx-layer realm-fx-shimmer"></div>';
        echo '<div class="realm-fx-layer realm-fx-cosmic"></div>';
        echo '<div class="realm-fx-layer realm-fx-rays"></div>';
        echo '<div class="realm-fx-layer realm-fx-runes"></div>';
        echo '<div class="realm-fx-particles-host"></div>';
        echo '<div class="realm-fx-layer realm-fx-dao-overlay"></div>';
        echo '<div class="realm-fx-layer realm-fx-bloodline-overlay"></div>';
        echo '</div>';
    }
}
