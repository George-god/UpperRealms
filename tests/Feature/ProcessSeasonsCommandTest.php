<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SeasonService;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ProcessSeasonsCommandTest extends TestCase
{
    public function test_reports_processed_seasons(): void
    {
        $this->mock(SeasonService::class, function (MockInterface $mock) {
            $mock->shouldReceive('processEndedSeasons')->once()->withNoArgs()
                ->andReturn(['ok' => true, 'processed' => 2, 'detail' => []]);
        });

        $this->artisan('game:process-seasons')
            ->expectsOutput('Processed 2 expired season(s).')->assertSuccessful();
    }

    public function test_no_expired_seasons_is_a_successful_run(): void
    {
        $this->mock(SeasonService::class, function (MockInterface $mock) {
            $mock->shouldReceive('processEndedSeasons')->once()->withNoArgs()
                ->andReturn(['ok' => true, 'processed' => 0, 'detail' => []]);
        });

        $this->artisan('game:process-seasons')
            ->expectsOutput('Processed 0 expired season(s).')->assertSuccessful();
    }

    public function test_service_failure_returns_nonzero_without_exposing_details(): void
    {
        $this->mock(SeasonService::class, function (MockInterface $mock) {
            $mock->shouldReceive('processEndedSeasons')->once()
                ->andReturn(['ok' => false, 'processed' => 0, 'detail' => [], 'error' => 'sensitive-test-marker']);
        });

        $this->artisan('game:process-seasons')->expectsOutput('Season processing failed.')
            ->doesntExpectOutputToContain('sensitive-test-marker')->assertFailed();
    }

    public function test_unexpected_exception_is_sanitized_and_returns_nonzero(): void
    {
        $this->mock(SeasonService::class, function (MockInterface $mock) {
            $mock->shouldReceive('processEndedSeasons')->once()
                ->andThrow(new RuntimeException('sensitive-test-marker'));
        });

        $this->artisan('game:process-seasons')->expectsOutput('Season processing failed.')
            ->doesntExpectOutputToContain('sensitive-test-marker')->assertFailed();
    }
}
