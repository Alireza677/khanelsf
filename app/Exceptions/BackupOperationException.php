<?php

namespace App\Exceptions;

use RuntimeException;

class BackupOperationException extends RuntimeException
{
    public function __construct(public readonly string $failureCode, string $safeMessage, ?\Throwable $previous = null)
    {
        parent::__construct($safeMessage, 0, $previous);
    }
}
