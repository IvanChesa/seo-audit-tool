<?php

namespace Tests\Feature\Analysis;

use App\Analysis\Analyzers\LinksAnalyzer;
use App\Analysis\SectionResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinksAnalyzerTest extends TestCase
{
    private function analyze(string $body): SectionResult
    {
        return $this->app->make(LinksAnalyzer::class)->analyze($this->context("<html><body>{$body}</body></html>"));
    }

    /**
     * @return list<string>
     */
    private function issueCodes(SectionResult $result): array
    {
        return array_map(fn ($issue) => $issue->code, $result->issues);
    }

    public function test_links_are_counted_and_checked(): void
    {
        Http::fake([
            'https://example.com/ok' => Http::response('', 200),
            'https://example.com/roto' => Http::response('', 404),
            'https://other.org/*' => Http::response('', 200),
        ]);

        $result = $this->analyze('<a href="/ok">Bien</a><a href="/ok">Duplicado</a><a href="/roto">Roto</a><a href="https://other.org/x" rel="nofollow">Fuera</a>');

        $this->assertSame(['links' => 4, 'unique' => 3, 'internal' => 2, 'external' => 1, 'nofollow' => 1, 'without_text' => 0], $result->data['totals']);
        $this->assertSame(['broken_internal_links'], $this->issueCodes($result));
        $this->assertSame([[
            'url' => 'https://example.com/roto',
            'is_internal' => true,
            'status_code' => 404,
            'error' => null,
            'link_text' => 'Roto',
        ]], $result->brokenLinks);
    }

    public function test_head_is_tried_first_and_get_is_used_when_head_is_rejected(): void
    {
        Http::fake(function (Request $request) {
            return $request->method() === 'HEAD'
                ? Http::response('', 405)
                : Http::response('<html></html>', 200);
        });

        $result = $this->analyze('<a href="/solo-get">Enlace</a>');

        $this->assertSame([], $result->brokenLinks);
        Http::assertSent(fn (Request $request) => $request->method() === 'HEAD');
        Http::assertSent(fn (Request $request) => $request->method() === 'GET');
    }

    public function test_redirects_are_followed_and_revalidated(): void
    {
        $this->dns->set('evil.example.net', ['10.0.0.1']);
        Http::fake([
            'https://example.com/antigua' => Http::response('', 301, ['Location' => '/nueva']),
            'https://example.com/nueva' => Http::response('', 200),
            'https://example.com/trampa' => Http::response('', 302, ['Location' => 'http://evil.example.net/admin']),
        ]);

        $result = $this->analyze('<a href="/antigua">A</a><a href="/trampa">B</a>');

        $this->assertSame([], $result->brokenLinks);
        $this->assertSame(1, $result->data['checked']['skipped']);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'evil.example.net'));
    }

    public function test_links_to_private_addresses_are_never_requested(): void
    {
        Http::fake();

        $result = $this->analyze('<a href="http://127.0.0.1:8080/admin">Admin</a><a href="http://169.254.169.254/latest/meta-data/">Meta</a><a href="http://localhost/">Local</a>');

        $this->assertSame(3, $result->data['checked']['skipped']);
        $this->assertSame([], $result->brokenLinks);
        Http::assertNothingSent();
    }

    public function test_restricted_responses_are_not_reported_as_broken(): void
    {
        Http::fake(['https://other.org/*' => Http::response('', 403)]);

        $result = $this->analyze('<a href="https://other.org/perfil">Perfil</a>');

        $this->assertSame([], $result->brokenLinks);
        $this->assertSame(1, $result->data['checked']['restricted']);
    }

    public function test_connection_failures_count_as_broken_external_links(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $result = $this->analyze('<a href="https://other.org/caido">Caído</a>');

        $this->assertSame(['broken_external_links', 'no_internal_links'], $this->issueCodes($result));
        $this->assertSame('connection_failed', $result->brokenLinks[0]['error']);
        $this->assertNull($result->brokenLinks[0]['status_code']);
    }

    public function test_only_the_configured_number_of_links_is_checked_internal_first(): void
    {
        config(['seo-audit.links.max_checked' => 2]);
        Http::fake(['*' => Http::response('', 200)]);

        $result = $this->analyze('<a href="https://other.org/1">E1</a><a href="/a">I1</a><a href="/b">I2</a><a href="/c">I3</a>');

        $this->assertSame(['limit' => 2, 'checked' => 2, 'broken' => 0, 'restricted' => 0, 'skipped' => 0, 'unchecked' => 2], $result->data['checked']);
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'other.org'));
    }

    public function test_links_without_text_and_pages_without_internal_links(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $result = $this->analyze('<a href="https://other.org/"><img src="x.png"></a>');

        $this->assertSame(['no_internal_links', 'links_without_text'], $this->issueCodes($result));
    }

    public function test_a_page_without_links(): void
    {
        Http::fake();

        $result = $this->analyze('<p>Sin enlaces</p>');

        $this->assertSame(0, $result->data['totals']['links']);
        $this->assertSame(['no_internal_links'], $this->issueCodes($result));
        Http::assertNothingSent();
    }
}
