<?php

namespace App\Analysis;

/**
 * The downloaded page, shared by every analyzer of an audit through the
 * cache. Its size is bounded by seo-audit.fetch.max_bytes.
 */
final readonly class PageSnapshot
{
    /**
     * @param  list<array{url: string, status: int}>  $redirects
     */
    public function __construct(
        public string $requestedUrl,
        public string $finalUrl,
        public int $statusCode,
        public ?string $contentType,
        public ?string $xRobotsTag,
        public string $html,
        public array $redirects,
        public int $responseTimeMs,
    ) {}

    public function bytes(): int
    {
        return strlen($this->html);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'requested_url' => $this->requestedUrl,
            'final_url' => $this->finalUrl,
            'status_code' => $this->statusCode,
            'content_type' => $this->contentType,
            'x_robots_tag' => $this->xRobotsTag,
            'html' => $this->html,
            'redirects' => $this->redirects,
            'response_time_ms' => $this->responseTimeMs,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            requestedUrl: (string) $data['requested_url'],
            finalUrl: (string) $data['final_url'],
            statusCode: (int) $data['status_code'],
            contentType: isset($data['content_type']) ? (string) $data['content_type'] : null,
            xRobotsTag: isset($data['x_robots_tag']) ? (string) $data['x_robots_tag'] : null,
            html: (string) $data['html'],
            redirects: array_values((array) ($data['redirects'] ?? [])),
            responseTimeMs: (int) ($data['response_time_ms'] ?? 0),
        );
    }
}
