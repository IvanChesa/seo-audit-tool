<?php

namespace App\Security;

use RuntimeException;

/**
 * Thrown when a URL (typed by the user, found in a page or received in a
 * redirect) must not be requested by the server.
 */
final class UnsafeUrlException extends RuntimeException
{
    public function __construct(
        public readonly UrlRejection $reason,
        public readonly bool $duringRedirect = false,
    ) {
        parent::__construct($reason->message());
    }

    public function asRedirect(): self
    {
        return new self($this->reason, duringRedirect: true);
    }
}
