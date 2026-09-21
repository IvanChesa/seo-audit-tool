<?php

namespace Tests\Feature\Analysis;

use App\Analysis\Analyzers\TechnicalAnalyzer;
use App\Analysis\SectionResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TechnicalAnalyzerTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function analyze(string $html, array $overrides = []): SectionResult
    {
        return $this->app->make(TechnicalAnalyzer::class)->analyze($this->context($html, $overrides));
    }

    /**
     * @return list<string>
     */
    private function issueCodes(SectionResult $result): array
    {
        return array_map(fn ($issue) => $issue->code, $result->issues);
    }

    /**
     * @return array<string, string>
     */
    private function checkValues(SectionResult $result): array
    {
        return array_column($result->data['checks'], 'status', 'key');
    }

    private function fakeCrawlFiles(string $robots = "User-agent: *\nAllow: /\nSitemap: https://example.com/sitemap.xml", int $sitemapStatus = 200): void
    {
        Http::fake([
            'https://example.com/robots.txt' => Http::response($robots, 200, ['Content-Type' => 'text/plain']),
            'https://example.com/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset></urlset>', $sitemapStatus, ['Content-Type' => 'application/xml']),
            'https://example.com/sitemap_index.xml' => Http::response('Not found', 404),
        ]);
    }

    public function test_a_well_configured_site_passes_every_check(): void
    {
        $this->fakeCrawlFiles();

        $result = $this->analyze($this->fixture('good-page.html'));

        $this->assertSame([], $this->issueCodes($result));
        $this->assertSame(100, $result->score);
        $this->assertSame('es', $result->data['lang']);
        $this->assertTrue($result->data['https']);
        $this->assertSame('found', $result->data['robots_txt']['status']);
        $this->assertFalse($result->data['robots_txt']['blocks_page']);
        $this->assertSame(['url' => 'https://example.com/sitemap.xml', 'status' => 'found', 'source' => 'robots_txt', 'http_status' => 200], $result->data['sitemap']);
    }

    public function test_it_reports_http_without_tls_missing_lang_and_viewport(): void
    {
        Http::fake([
            'http://example.com/robots.txt' => Http::response('', 404),
            'http://example.com/sitemap.xml' => Http::response('', 404),
            'http://example.com/sitemap_index.xml' => Http::response('', 404),
        ]);

        $result = $this->analyze($this->fixture('bad-page.html'), ['finalUrl' => 'http://example.com/']);

        $this->assertSame(
            ['no_https', 'missing_viewport', 'missing_lang', 'missing_robots_txt', 'missing_sitemap'],
            $this->issueCodes($result),
        );
        $this->assertSame('fail', $this->checkValues($result)['https']);
    }

    public function test_a_page_blocked_by_robots_txt_is_a_critical_issue(): void
    {
        $this->fakeCrawlFiles("User-agent: *\nDisallow: /privado/");

        $result = $this->analyze($this->fixture('good-page.html'), ['finalUrl' => 'https://example.com/privado/pagina']);

        $this->assertContains('blocked_by_robots_txt', $this->issueCodes($result));
        $this->assertSame('critical', $result->issues[0]->severity->value);
        $this->assertStringContainsString('Disallow: /privado/', (string) $result->issues[0]->evidence);
        $this->assertTrue($result->data['robots_txt']['blocks_page']);
    }

    public function test_a_declared_sitemap_that_does_not_exist_is_reported(): void
    {
        $this->fakeCrawlFiles(sitemapStatus: 404);

        $result = $this->analyze($this->fixture('good-page.html'));

        $this->assertSame(['sitemap_not_found'], $this->issueCodes($result));
    }

    public function test_the_default_sitemap_locations_are_tried_in_order(): void
    {
        Http::fake([
            'https://example.com/robots.txt' => Http::response("User-agent: *\nDisallow:", 200, ['Content-Type' => 'text/plain']),
            'https://example.com/sitemap.xml' => Http::response('', 404),
            'https://example.com/sitemap_index.xml' => Http::response('<sitemapindex></sitemapindex>', 200, ['Content-Type' => 'text/xml']),
        ]);

        $result = $this->analyze($this->fixture('good-page.html'));

        $this->assertSame('https://example.com/sitemap_index.xml', $result->data['sitemap']['url']);
        $this->assertSame('default', $result->data['sitemap']['source']);
    }

    public function test_an_html_page_served_as_robots_txt_counts_as_missing(): void
    {
        Http::fake([
            'https://example.com/robots.txt' => Http::response('<!doctype html><html>Home</html>', 200, ['Content-Type' => 'text/html']),
            'https://example.com/sitemap*' => Http::response('<!doctype html><html>Home</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = $this->analyze($this->fixture('good-page.html'));

        $this->assertContains('missing_robots_txt', $this->issueCodes($result));
        $this->assertContains('missing_sitemap', $this->issueCodes($result));
    }

    public function test_unreachable_crawl_files_are_unknown_and_not_penalised(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $result = $this->analyze($this->fixture('good-page.html'));

        $this->assertSame([], $this->issueCodes($result));
        $this->assertSame('unknown', $this->checkValues($result)['robots_txt']);
        $this->assertSame('unknown', $this->checkValues($result)['sitemap']);
    }

    public function test_redirect_chains_slow_responses_and_viewport_problems(): void
    {
        $this->fakeCrawlFiles();
        $html = '<html lang="es-ES"><head><meta name="viewport" content="width=device-width, user-scalable=no"></head><body></body></html>';

        $result = $this->analyze($html, [
            'redirects' => [
                ['url' => 'http://example.com/', 'status' => 301],
                ['url' => 'https://example.com/index', 'status' => 302],
            ],
            'responseTimeMs' => 4200,
        ]);

        $this->assertSame(['redirect_chain', 'slow_server_response', 'viewport_blocks_zoom'], $this->issueCodes($result));
        $this->assertStringContainsString('http://example.com/ (301) → https://example.com/index (302) → https://example.com/', (string) $result->issues[0]->evidence);
    }

    public function test_robots_txt_is_requested_through_the_safe_client(): void
    {
        // The audited site claims its sitemap lives on an internal host.
        $this->fakeCrawlFiles("User-agent: *\nDisallow:\nSitemap: http://169.254.169.254/latest/meta-data/");

        $result = $this->analyze($this->fixture('good-page.html'));

        $this->assertSame('unknown', $result->data['sitemap']['status']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '169.254.169.254'));
    }
}
