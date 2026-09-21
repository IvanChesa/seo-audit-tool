<?php

/*
|--------------------------------------------------------------------------
| SEO Audit configuration
|--------------------------------------------------------------------------
|
| Every limit that protects the server when downloading third-party pages
| lives here, so it can be tuned per environment without touching code.
| See the "Seguridad" section of the README for the reasoning behind them.
|
*/

$csv = static fn (string $value): array => array_values(array_filter(array_map('trim', explode(',', $value))));

return [

    'user_agent' => env('AUDIT_USER_AGENT', 'SEO-Audit-Tool/1.0 (+https://github.com/IvanChesa/seo-audit-tool)'),

    // Main page download.
    'fetch' => [
        'connect_timeout' => (int) env('AUDIT_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('AUDIT_FETCH_TIMEOUT', 15),
        'max_bytes' => (int) env('AUDIT_MAX_PAGE_BYTES', 2 * 1024 * 1024),
        'max_redirects' => (int) env('AUDIT_MAX_REDIRECTS', 5),
    ],

    // Only these ports may be requested (the default ones for http/https
    // plus the usual alternatives). Anything else is rejected before DNS.
    'allowed_ports' => array_map('intval', $csv((string) env('AUDIT_ALLOWED_PORTS', '80,443,8080,8443'))),

    // Broken link checker.
    'links' => [
        'max_checked' => (int) env('AUDIT_MAX_LINKS_CHECKED', 30),
        'concurrency' => (int) env('AUDIT_LINK_CONCURRENCY', 5),
        'timeout' => (int) env('AUDIT_LINK_TIMEOUT', 8),
        'max_redirects' => 3,
        'max_bytes' => 256 * 1024,
    ],

    // robots.txt and sitemap presence checks.
    'crawl_files' => [
        'timeout' => (int) env('AUDIT_CRAWL_FILES_TIMEOUT', 8),
        'max_bytes' => 512 * 1024,
    ],

    // Downloaded HTML is kept in the cache only while the analyzers run.
    'snapshot_ttl_minutes' => (int) env('AUDIT_SNAPSHOT_TTL_MINUTES', 60),

    'rate_limit' => [
        'create_per_minute' => (int) env('AUDIT_RATE_LIMIT_PER_MINUTE', 10),
        'create_per_day' => (int) env('AUDIT_RATE_LIMIT_PER_DAY', 200),
        'api_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),
    ],

    'retention' => [
        // Audits older than this are deleted by `audits:prune`. Opt-in: the
        // default (0) keeps the history forever.
        'days' => (int) env('AUDIT_RETENTION_DAYS', 0),
        // Audits stuck in pending/processing longer than this are marked as failed.
        'stale_after_minutes' => (int) env('AUDIT_STALE_AFTER_MINUTES', 15),
    ],

    'pagespeed' => [
        'strategy' => env('PAGESPEED_STRATEGY', 'mobile'),
        'timeout' => (int) env('PAGESPEED_TIMEOUT', 90),
    ],

];
