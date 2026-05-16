<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Attributes\AttributeCalculator;
use Tests\TestCase;

class AttributeCalculatorTest extends TestCase
{
    public function test_derives_higher_attack_from_strength(): void
    {
        $calc = new AttributeCalculator;

        $low = $calc->compute([
            'strength' => 5,
            'agility' => 5,
            'vitality' => 5,
            'spirit' => 5,
            'soul' => 5,
            'willpower' => 5,
        ]);

        $high = $calc->compute([
            'strength' => 25,
            'agility' => 5,
            'vitality' => 5,
            'spirit' => 5,
            'soul' => 5,
            'willpower' => 5,
        ]);

        $this->assertGreaterThan($low['flat_attack'], $high['flat_attack']);
    }

    public function test_synergy_triggers_when_requirements_met(): void
    {
        $calc = new AttributeCalculator;
        $result = $calc->compute([
            'strength' => 20,
            'agility' => 5,
            'vitality' => 20,
            'spirit' => 5,
            'soul' => 5,
            'willpower' => 5,
        ]);

        $ids = array_column($result['active_synergies'], 'id');
        $this->assertContains('iron_body', $ids);
    }
}
