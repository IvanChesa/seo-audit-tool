<?php

namespace App\Analysis\Support;

final class UrlComparison
{
    /**
     * Whether two absolute URLs point to the same resource, ignoring case in
     * scheme/host, default ports, fragments and an empty path vs "/".
     */
    public static function same(string $a, string $b): bool
    {
        return self::canonicalForm($a) === self::canonicalForm($b);
    }

    public static function hostOf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower(rtrim($host, '.')) : null;
    }

    /**
     * Hosts are considered the same site when they only differ by "www.".
     */
    public static function sameSite(?string $hostA, ?string $hostB): bool
    {
        if ($hostA === null || $hostB === null) {
            return false;
        }

        return self::withoutWww($hostA) === self::withoutWww($hostB);
    }

    private static function withoutWww(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    private static function canonicalForm(string $url): string
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return trim($url);
        }

        $scheme = strtolower($parts['scheme']);
        $port = $parts['port'] ?? null;
        $defaultPort = $scheme === 'https' ? 443 : 80;
        $authority = strtolower(rtrim($parts['host'], '.')).($port !== null && $port !== $defaultPort ? ":{$port}" : '');
        $path = ($parts['path'] ?? '') === '' ? '/' : $parts['path'];
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return "{$scheme}://{$authority}{$path}{$query}";
    }
}
