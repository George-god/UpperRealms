<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use Throwable;

final class SeasonLifecycleLockException extends RuntimeException
{
    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct('Season lifecycle lock: '.$reason, 0, $previous);
    }
}
