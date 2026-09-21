<?php

namespace Tests\Feature\Security;

use App\Security\Http\BoundedSink;
use App\Security\Http\RequestLimits;
use App\Security\Http\ResponseTooLargeException;
use App\Security\Http\SafeHttpClient;
use App\Security\Http\TooManyRedirectsException;
use App\Security\Http\TransferFailedException;
use App\Security\UnsafeUrlException;
use App\Security\UrlGuard;
use App\Security\UrlRejection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SafeHttpClientTest extends TestCase
{
    private function client(): SafeHttpClient
    {
        return $this->app->make(SafeHttpClient::class);
    }

    private function limits(int $maxBytes = 10_000, int $maxRedirects = 3): RequestLimits
    {
        return new RequestLimits(connectTimeout: 2, timeout: 5, maxBytes: $maxBytes, maxRedirects: $maxRedirects);
    }

    public function test_it_downloads_a_public_page(): void
    {
        Http::fake(['https://example.com/' => Http::response('<html>ok</html>', 200, ['Content-Type' => 'text/html'])]);

        $response = $this->client()->get('https://example.com', $this->limits());

        $this->assertSame(200, $response->status);
        $this->assertSame('<html>ok</html>', $response->body);
        $this->assertSame('text/html', $response->mediaType());
        $this->assertSame([], $response->redirects);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://example.com/'
            && $request->header('Accept-Encoding') === ['identity']
            && str_starts_with($request->header('User-Agent')[0] ?? '', 'SEO-Audit-Tool'));
    }

    public function test_it_follows_safe_redirects_and_records_the_chain(): void
    {
        Http::fake([
            'http://example.com/' => Http::response('', 301, ['Location' => 'https://example.com/']),
            'https://example.com/' => Http::response('', 302, ['Location' => '/inicio']),
            'https://example.com/inicio' => Http::response('<html>final</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $response = $this->client()->get('http://example.com/', $this->limits());

        $this->assertSame('https://example.com/inicio', $response->url->toString());
        $this->assertSame([
            ['url' => 'http://example.com/', 'status' => 301],
            ['url' => 'https://example.com/', 'status' => 302],
        ], $response->redirects);
    }

    /**
     * @return array<string, array{string, UrlRejection}>
     */
    public static function dangerousRedirectTargets(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/admin', UrlRejection::PrivateAddress],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/iam/', UrlRejection::PrivateAddress],
            'private network' => ['http://10.0.0.8:8080/', UrlRejection::PrivateAddress],
            'IPv6 loopback' => ['http://[::1]/', UrlRejection::PrivateAddress],
            'localhost name' => ['http://localhost/', UrlRejection::LocalHostname],
            'internal service name' => ['http://redis:6379/', UrlRejection::LocalHostname],
            'file scheme' => ['file:///etc/passwd', UrlRejection::UnsupportedScheme],
            'gopher scheme' => ['gopher://example.com/', UrlRejection::UnsupportedScheme],
            'credentials' => ['https://user:pass@example.com/', UrlRejection::EmbeddedCredentials],
            'non-web port' => ['http://example.com:22/', UrlRejection::DisallowedPort],
            'host resolving to private IP' => ['https://internal.attacker.com/', UrlRejection::PrivateAddress],
        ];
    }

    #[DataProvider('dangerousRedirectTargets')]
    public function test_redirects_to_unsafe_targets_are_blocked_before_any_request(string $target, UrlRejection $reason): void
    {
        $this->dns->set('internal.attacker.com', ['192.168.10.20']);
        Http::fake(['https://example.com/*' => Http::response('', 302, ['Location' => $target])]);

        try {
            $this->client()->get('https://example.com/start', $this->limits());
            $this->fail('The unsafe redirect should have been blocked.');
        } catch (UnsafeUrlException $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertTrue($e->duringRedirect);
        }

        // Only the first request was made: the redirect target was never contacted.
        Http::assertSentCount(1);
    }

    public function test_a_hostname_that_resolves_to_a_private_ip_is_never_requested(): void
    {
        $this->dns->set('rebind.attacker.com', ['127.0.0.1']);
        Http::fake();

        $this->expectException(UnsafeUrlException::class);

        try {
            $this->client()->get('https://rebind.attacker.com/', $this->limits());
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_the_number_of_redirects_is_limited(): void
    {
        Http::fake(['https://example.com/*' => Http::response('', 302, ['Location' => '/loop'])]);

        $this->expectException(TooManyRedirectsException::class);

        try {
            $this->client()->get('https://example.com/loop', $this->limits(maxRedirects: 2));
        } finally {
            Http::assertSentCount(3);
        }
    }

    public function test_responses_larger_than_the_limit_are_rejected(): void
    {
        Http::fake(['https://example.com/' => Http::response(str_repeat('a', 2_000), 200, ['Content-Type' => 'text/html'])]);

        $this->expectException(ResponseTooLargeException::class);

        $this->client()->get('https://example.com/', $this->limits(maxBytes: 1_000));
    }

    public function test_connection_failures_become_user_friendly_transfer_errors(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 5001 milliseconds'));

        try {
            $this->client()->get('https://example.com/', $this->limits());
            $this->fail('Expected a transfer failure.');
        } catch (TransferFailedException $e) {
            $this->assertSame('timeout', $e->errorCode);
            $this->assertTrue($e->retryable);
            $this->assertStringNotContainsString('cURL', $e->getMessage());
        }
    }

    public function test_transfer_options_pin_the_validated_ip_and_disable_automatic_redirects(): void
    {
        $target = $this->app->make(UrlGuard::class)->inspect('https://example.com/page');

        $options = $this->client()->transferOptions($target, $this->limits(), new BoundedSink(100));

        $this->assertSame(['example.com:443:'.self::PUBLIC_IP], $options['curl'][CURLOPT_RESOLVE]);
        $this->assertFalse($options['allow_redirects']);
        $this->assertSame('', $options['proxy']);
        $this->assertSame(['http', 'https'], $options['protocols']);
        $this->assertFalse($options['decode_content']);
        $this->assertIsCallable($options['on_headers']);
    }

    public function test_the_bounded_sink_refuses_to_grow_past_its_limit(): void
    {
        $sink = new BoundedSink(10);

        $this->assertSame(6, $sink->write('123456'));
        $this->assertFalse($sink->limitExceeded());
        // Returning 0 makes cURL abort the transfer (CURLE_WRITE_ERROR).
        $this->assertSame(0, $sink->write('789012'));
        $this->assertTrue($sink->limitExceeded());
        $this->assertSame('123456', (string) $sink);
    }
}
