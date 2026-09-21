<?php

namespace App\Security\Http;

/**
 * Hard limits applied to every outgoing request.
 */
final readonly class RequestLimits
{
    public function __construct(
        public int $connectTimeout,
        public int $timeout,
        public int $maxBytes,
        public int $maxRedirects,
    ) {}

    public static function forPage(): self
    {
        return new self(
            connectTimeout: (int) config('seo-audit.fetch.connect_timeout'),
            timeout: (int) config('seo-audit.fetch.timeout'),
            maxBytes: (int) config('seo-audit.fetch.max_bytes'),
            maxRedirects: (int) config('seo-audit.fetch.max_redirects'),
        );
    }

    public static function forCrawlFile(): self
    {
        return new self(
            connectTimeout: (int) config('seo-audit.fetch.connect_timeout'),
            timeout: (int) config('seo-audit.crawl_files.timeout'),
            maxBytes: (int) config('seo-audit.crawl_files.max_bytes'),
            maxRedirects: (int) config('seo-audit.fetch.max_redirects'),
        );
    }

    public static function forLinkCheck(): self
    {
        return new self(
            connectTimeout: (int) config('seo-audit.fetch.connect_timeout'),
            timeout: (int) config('seo-audit.links.timeout'),
            maxBytes: (int) config('seo-audit.links.max_bytes'),
            maxRedirects: (int) config('seo-audit.links.max_redirects'),
        );
    }
}
