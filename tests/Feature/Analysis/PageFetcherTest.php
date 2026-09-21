<?php

namespace Tests\Feature\Analysis;

use App\Analysis\PageFetcher;
use App\Analysis\PageFetchException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PageFetcherTest extends TestCase
{
    private function fetchFailure(string $url = 'https://example.com/'): PageFetchException
    {
        try {
            $this->app->make(PageFetcher::class)->fetch($url);
        } catch (PageFetchException $e) {
            return $e;
        }

        $this->fail('Expected the fetch to fail.');
    }

    public function test_it_returns_a_snapshot_of_an_html_page(): void
    {
        Http::fake([
            'http://example.com/' => Http::response('', 301, ['Location' => 'https://example.com/']),
            'https://example.com/' => Http::response('<html></html>', 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Robots-Tag' => 'noarchive',
            ]),
        ]);

        $snapshot = $this->app->make(PageFetcher::class)->fetch('http://example.com/');

        $this->assertSame('http://example.com/', $snapshot->requestedUrl);
        $this->assertSame('https://example.com/', $snapshot->finalUrl);
        $this->assertSame(200, $snapshot->statusCode);
        $this->assertSame('noarchive', $snapshot->xRobotsTag);
        $this->assertSame([['url' => 'http://example.com/', 'status' => 301]], $snapshot->redirects);
    }

    public function test_html_without_content_type_is_accepted(): void
    {
        Http::fake(['*' => Http::response("  <!DOCTYPE html>\n<html><body>Hola</body></html>", 200)]);

        $this->assertSame(200, $this->app->make(PageFetcher::class)->fetch('https://example.com/')->statusCode);
    }

    /**
     * @return array<string, array{int, array<string, string>, string, bool}>
     */
    public static function unusableResponses(): array
    {
        return [
            'not found' => [404, ['Content-Type' => 'text/html'], 'http_error', false],
            'gone' => [410, ['Content-Type' => 'text/html'], 'http_error', false],
            'server error is retryable' => [503, ['Content-Type' => 'text/html'], 'http_server_error', true],
            'PDF' => [200, ['Content-Type' => 'application/pdf'], 'not_html', false],
            'JSON' => [200, ['Content-Type' => 'application/json'], 'not_html', false],
        ];
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[DataProvider('unusableResponses')]
    public function test_unusable_responses_fail_with_a_clear_reason(int $status, array $headers, string $code, bool $retryable): void
    {
        Http::fake(['*' => Http::response('%PDF-1.7 or anything', $status, $headers)]);

        $exception = $this->fetchFailure();

        $this->assertSame($code, $exception->errorCode);
        $this->assertSame($retryable, $exception->retryable);
    }

    public function test_pages_over_the_size_limit_are_rejected(): void
    {
        config(['seo-audit.fetch.max_bytes' => 1024]);
        Http::fake(['*' => Http::response(str_repeat('<p>x</p>', 500), 200, ['Content-Type' => 'text/html'])]);

        $this->assertSame('page_too_large', $this->fetchFailure()->errorCode);
    }

    public function test_redirects_to_internal_addresses_are_reported_as_unsafe(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/'])]);

        $exception = $this->fetchFailure();

        $this->assertSame('unsafe_redirect', $exception->errorCode);
        $this->assertFalse($exception->retryable);
    }

    public function test_a_url_that_became_unsafe_after_validation_is_rejected(): void
    {
        // DNS answer changed between the API validation and the job (rebinding).
        $this->dns->set('example.com', ['127.0.0.1']);
        Http::fake();

        $this->assertSame('unsafe_url', $this->fetchFailure()->errorCode);
        Http::assertNothingSent();
    }

    public function test_timeouts_are_retryable_and_the_message_hides_internals(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 15001 milliseconds with 0 bytes received'));

        $exception = $this->fetchFailure();

        $this->assertSame('timeout', $exception->errorCode);
        $this->assertTrue($exception->retryable);
        $this->assertStringNotContainsString('cURL', $exception->getMessage());
    }

    public function test_too_many_redirects(): void
    {
        config(['seo-audit.fetch.max_redirects' => 2]);
        Http::fake(['*' => Http::response('', 301, ['Location' => '/otra'])]);

        $this->assertSame('too_many_redirects', $this->fetchFailure()->errorCode);
    }
}
