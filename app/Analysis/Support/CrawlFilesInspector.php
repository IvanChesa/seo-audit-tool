<?php

namespace App\Analysis\Support;

use App\Security\Http\RequestLimits;
use App\Security\Http\ResponseTooLargeException;
use App\Security\Http\SafeHttpClient;
use App\Security\Http\SafeResponse;
use App\Security\Http\TooManyRedirectsException;
use App\Security\Http\TransferFailedException;
use App\Security\UnsafeUrlException;

/**
 * Looks for robots.txt and the XML sitemap of a site. Every request goes
 * through SafeHttpClient, because a robots.txt can point to any host.
 */
final class CrawlFilesInspector
{
    public const FOUND = 'found';

    public const MISSING = 'missing';

    public const UNKNOWN = 'unknown';

    private const DEFAULT_SITEMAPS = ['/sitemap.xml', '/sitemap_index.xml'];

    public function __construct(private readonly SafeHttpClient $client) {}

    /**
     * @return array{url: string, status: string, http_status: int|null, robots: RobotsTxt|null}
     */
    public function robotsTxt(string $origin): array
    {
        $url = $origin.'/robots.txt';
        $response = $this->request($url);

        if (! $response instanceof SafeResponse) {
            return ['url' => $url, 'status' => $response, 'http_status' => null, 'robots' => null];
        }

        // Some sites answer every path with their HTML home page.
        $isHtml = $response->mediaType() === 'text/html' || preg_match('/^\s*<(!doctype|html)/i', $response->body) === 1;

        if ($response->successful() && ! $isHtml) {
            return ['url' => $url, 'status' => self::FOUND, 'http_status' => $response->status, 'robots' => RobotsTxt::parse($response->body)];
        }

        // RFC 9309: 4xx means "no restrictions"; 5xx means the file is temporarily unreachable.
        $status = $response->status >= 500 ? self::UNKNOWN : self::MISSING;

        return ['url' => $url, 'status' => $status, 'http_status' => $response->status, 'robots' => null];
    }

    /**
     * @param  list<string>  $declared  Sitemap URLs listed in robots.txt.
     * @return array{url: string|null, status: string, source: string, http_status: int|null}
     */
    public function sitemap(string $origin, array $declared): array
    {
        $source = $declared !== [] ? 'robots_txt' : 'default';
        $candidates = $declared !== []
            ? array_slice($declared, 0, 1)
            : array_map(fn (string $path) => $origin.$path, self::DEFAULT_SITEMAPS);

        $last = ['url' => $candidates[0], 'status' => self::MISSING, 'source' => $source, 'http_status' => null];

        foreach ($candidates as $url) {
            $response = $this->request($url);

            if ($response === self::FOUND) {
                return ['url' => $url, 'status' => self::FOUND, 'source' => $source, 'http_status' => null];
            }

            if (! $response instanceof SafeResponse) {
                $last = ['url' => $url, 'status' => $response, 'source' => $source, 'http_status' => null];

                continue;
            }

            if ($response->successful() && $this->looksLikeSitemap($url, $response)) {
                return ['url' => $url, 'status' => self::FOUND, 'source' => $source, 'http_status' => $response->status];
            }

            $last = [
                'url' => $url,
                'status' => $response->status >= 500 ? self::UNKNOWN : self::MISSING,
                'source' => $source,
                'http_status' => $response->status,
            ];
        }

        return $last;
    }

    /**
     * @return SafeResponse|string A response, or FOUND for a file too large to
     *                             download (it exists), or UNKNOWN on failure.
     */
    private function request(string $url): SafeResponse|string
    {
        try {
            return $this->client->get($url, RequestLimits::forCrawlFile(), ['Accept' => 'text/plain, application/xml, text/xml, */*;q=0.5']);
        } catch (ResponseTooLargeException) {
            return self::FOUND;
        } catch (UnsafeUrlException|TooManyRedirectsException|TransferFailedException) {
            return self::UNKNOWN;
        }
    }

    private function looksLikeSitemap(string $url, SafeResponse $response): bool
    {
        $type = $response->mediaType() ?? '';

        return in_array($type, ['application/xml', 'text/xml', 'application/gzip', 'application/x-gzip'], true)
            || str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?: ''), '.gz')
            || preg_match('/<(urlset|sitemapindex)[\s>]/i', substr($response->body, 0, 4096)) === 1;
    }
}
