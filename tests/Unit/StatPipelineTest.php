<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\StatCalculator;
use App\Services\Stats\StatPipeline;
use PHPUnit\Framework\TestCase;

class StatPipelineTest extends TestCase
{
    public function test_pipeline_delegates_to_calculator(): void
    {
        $calculator = $this->createMock(StatCalculator::class);
        $calculator->expects($this->once())
            ->method('calculateFinalStats')
            ->with(42)
            ->willReturn(['user_id' => 42, 'final' => []]);

        $pipeline = new StatPipeline($calculator);
        $out = $pipeline->calculateFinalStats(42);

        $this->assertSame(42, $out['user_id']);
    }
}
