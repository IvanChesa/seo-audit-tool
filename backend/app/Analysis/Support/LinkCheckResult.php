<?php

namespace App\Analysis\Support;

final readonly class LinkCheckResult
{
    /**
     * Status codes that usually mean "bots are not welcome" rather than
     * "this page does not exist" (999 is LinkedIn's anti-bot response).
     */
    private const RESTRICTED_STATUSES = [401, 403, 429, 999];

    public function __construct(
        public LinkState $state,
        public ?int $statusCode = null,
        public ?string $error = null,
    ) {}

    public static function fromStatus(int $status): self
    {
        $state = match (true) {
            $status < 400 => LinkState::Ok,
            in_array($status, self::RESTRICTED_STATUSES, true) => LinkState::Restricted,
            default => LinkState::Broken,
        };

        return new self($state, $status);
    }

    public static function failed(string $error): self
    {
        return new self(LinkState::Broken, null, $error);
    }

    public static function skipped(): self
    {
        return new self(LinkState::Skipped);
    }
}
