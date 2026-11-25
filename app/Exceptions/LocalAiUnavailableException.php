<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class LocalAiUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
