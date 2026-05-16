<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Stats\StatBonusComposer;
use Tests\TestCase;

class StatBonusComposerTest extends TestCase
{
    public function test_pools_and_caps_percent_bonuses(): void
    {
        config(['stats.use_additive_percent_pool' => true]);
        config(['stats.max_pooled_attack_pct' => 2.5]);

        $composer = new StatBonusComposer;
        $result = $composer->applyPercentPool(
            ['attack' => 100, 'defense' => 50, 'max_chi' => 200, 'chi' => 200],
            ['attack_pct' => 0.5, 'defense_pct' => 0.2, 'max_chi_pct' => 0.1],
        );

        $this->assertSame(150, $result['attack']);
        $this->assertSame(60, $result['defense']);
        $this->assertSame(220, $result['max_chi']);
    }
}
