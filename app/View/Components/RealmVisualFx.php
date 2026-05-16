<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Ambient cultivation-realm visuals (CSS + optional JS particles). Tier is derived from realm_id only.
 */
class RealmVisualFx extends Component
{
    public function __construct(
        public int $realmId = 1,
        public ?string $daoFlair = null,
        public ?string $bloodlineFlair = null,
    ) {}

    public function tier(): string
    {
        $id = max(1, $this->realmId);

        return match (true) {
            $id <= 1 => 'qi',
            $id === 2 => 'foundation',
            $id === 3 => 'core',
            $id === 4 => 'nascent',
            $id === 5 => 'transformation',
            default => 'immortal',
        };
    }

    /**
     * Safe CSS suffix: fire, water, wood, metal, earth, ice, demonic, righteous, neutral.
     */
    public function daoClass(): string
    {
        $raw = strtolower((string) ($this->daoFlair ?? ''));
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

    /**
     * Safe CSS suffix: dragon, phoenix, demon, celestial, beast.
     */
    public function bloodlineClass(): string
    {
        $raw = strtolower((string) ($this->bloodlineFlair ?? ''));
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

    public function render(): View
    {
        return view('components.realm-visual-fx', [
            'fxTier' => $this->tier(),
            'fxDao' => $this->daoClass(),
            'fxBlood' => $this->bloodlineClass(),
        ]);
    }
}
