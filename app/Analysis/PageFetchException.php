<?php

namespace App\Analysis;

use RuntimeException;
use Throwable;

/**
 * The audited page could not be downloaded. The message is user-facing;
 * technical details, if any, travel in the previous exception (for logs).
 */
final class PageFetchException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
