<?php

namespace App\Security\Http;

use App\Security\SafeUrl;

final readonly class SafeResponse
{
    /**
     * @param  array<string, string>  $headers  Lower-cased header name => first value.
     * @param  list<array{url: string, status: int}>  $redirects  Hops followed before reaching $url.
     */
    public function __construct(
        public SafeUrl $url,
        public int $status,
        public array $headers,
        public string $body,
        public array $redirects,
        public int $durationMs,
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Media type without parameters, e.g. "text/html".
     */
    public function mediaType(): ?string
    {
        $contentType = $this->header('content-type');

        if ($contentType === null || trim($contentType) === '') {
            return null;
        }

        return strtolower(trim(explode(';', $contentType)[0]));
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
