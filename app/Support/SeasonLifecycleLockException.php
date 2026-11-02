<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use Throwable;

final class SeasonLifecycleLockException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        ?Throwable $previous = null,
        public readonly bool $discardConnection = false,
        public readonly array $cleanupFailures = [],
    )
    {
        parent::__construct('Season lifecycle lock: '.$reason, 0, $previous);
    }

    /** Diagnostic identities only: never serialize SQL, exception messages, or traces. */
    public function safeDiagnostics(): array
    {
        $causes = [];
        for ($cause = $this; $cause !== null && count($causes) < 8; $cause = $cause->getPrevious()) {
            $causes[] = ['type' => $cause::class, 'code' => (string) $cause->getCode()];
        }
        $cleanup = [];
        foreach ($this->cleanupFailures as $stage => $failure) {
            $cleanup[$stage] = ['type' => $failure::class, 'code' => (string) $failure->getCode()];
        }
        return ['causes' => $causes, 'cleanup' => $cleanup];
    }
}
