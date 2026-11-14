<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\StatCalculator;
use App\Services\Stats\PlayerStatCache;
use App\Services\Stats\StatPipeline;
use Tests\TestCase;

class StatPipelineTest extends TestCase
{
    public function test_pipeline_delegates_to_calculator(): void
    {
        config([
            'cache.default' => 'array',
            'stats.cache_enabled' => true,
            'stats.cache_prefix' => 'baseline_stat_pipeline_test',
        ]);
        $expected = ['user_id' => 42, 'final' => ['attack' => 123]];
        $calculator = $this->createMock(StatCalculator::class);
        $calculator->expects($this->once())
            ->method('clearFinalStatsCache');
        $calculator->expects($this->once())
            ->method('calculateFinalStats')
            ->with(42)
            ->willReturn($expected);

        $pipeline = new StatPipeline($calculator, new PlayerStatCache($calculator));
        $out = $pipeline->calculateFinalStats(42);

        $this->assertSame(42, $out['user_id']);
        $this->assertSame($expected, $out);
        $this->assertSame($expected, $pipeline->calculateFinalStats(42));
    }
}
