<?php

namespace App\Analysis\Support;

use App\Security\ResolvedUrl;

/**
 * State of one link while it is being checked (current hop and method).
 */
final readonly class LinkProbe
{
    public function __construct(
        public ResolvedUrl $target,
        public string $method = 'HEAD',
        public int $redirects = 0,
    ) {}

    public function withMethod(string $method): self
    {
        return new self($this->target, $method, $this->redirects);
    }

    public function redirectTo(ResolvedUrl $target): self
    {
        return new self($target, $this->method, $this->redirects + 1);
    }
}
