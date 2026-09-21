<?php

namespace App\Analysis\Support;

use RuntimeException;

final class PageSpeedException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly bool $retryable,
    ) {
        parent::__construct($message);
    }
}
