<?php

namespace App\Security\Http;

use App\Security\ResolvedUrl;
use App\Security\UnsafeUrlException;
use App\Security\UrlGuard;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\UriResolver;

/**
 * HTTP client for URLs that come from users or from third-party pages.
 *
 * Protections applied to every request:
 *  - the URL is validated and its host resolved by UrlGuard (no private,
 *    reserved, loopback, link-local or metadata addresses);
 *  - cURL is pinned to the validated IP (CURLOPT_RESOLVE), so a second DNS
 *    answer cannot redirect the connection (DNS rebinding);
 *  - redirects are followed manually and every hop is validated again;
 *  - connect/total timeouts and a hard limit on downloaded bytes;
 *  - no proxies from the environment and only http/https protocols.
 */
final class SafeHttpClient
{
    public const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    public function __construct(
        private readonly UrlGuard $guard,
        #[Config('seo-audit.user_agent')]
        private readonly string $userAgent,
    ) {}

    /**
     * @param  array<string, string>  $headers
     *
     * @throws UnsafeUrlException when the URL or any redirect target is not allowed
     * @throws TooManyRedirectsException
     * @throws ResponseTooLargeException
     * @throws TransferFailedException when no HTTP response could be obtained
     */
    public function get(string $url, RequestLimits $limits, array $headers = []): SafeResponse
    {
        $startedAt = hrtime(true);
        $target = $this->guard->inspect($url);
        $redirects = [];

        while (true) {
            $response = $this->send($target, $limits, $headers);
            $location = trim((string) $response->header('Location'));

            if (! in_array($response->status(), self::REDIRECT_STATUSES, true) || $location === '') {
                return new SafeResponse(
                    url: $target->url,
                    status: $response->status(),
                    headers: $this->flattenHeaders($response),
                    body: $response->body(),
                    redirects: $redirects,
                    durationMs: (int) round((hrtime(true) - $startedAt) / 1_000_000),
                );
            }

            if (count($redirects) >= $limits->maxRedirects) {
                throw new TooManyRedirectsException($limits->maxRedirects);
            }

            $redirects[] = ['url' => $target->url->toString(), 'status' => $response->status()];

            try {
                $target = $this->guard->inspect(UriResolver::resolve($location, $target->url->toString()));
            } catch (UnsafeUrlException $e) {
                throw $e->asRedirect();
            }
        }
    }

    /**
     * Applies every transfer restriction to a pending request. The link
     * checker uses it directly to send pinned requests through a pool.
     *
     * @param  array<string, string>  $headers
     */
    public function configure(
        PendingRequest $request,
        ResolvedUrl $target,
        RequestLimits $limits,
        BoundedSink $sink,
        array $headers = [],
    ): PendingRequest {
        return $request
            ->withOptions($this->transferOptions($target, $limits, $sink))
            ->withUserAgent($this->userAgent)
            ->withHeaders($headers + ['Accept-Encoding' => 'identity'])
            ->connectTimeout($limits->connectTimeout)
            ->timeout($limits->timeout);
    }

    /**
     * @return array<string, mixed>
     */
    public function transferOptions(ResolvedUrl $target, RequestLimits $limits, BoundedSink $sink): array
    {
        $options = [
            'allow_redirects' => false,
            'proxy' => '',
            'protocols' => ['http', 'https'],
            // Only uncompressed bodies are requested, so the byte limit is the
            // real size in memory and a compressed "bomb" cannot expand.
            'decode_content' => false,
            'sink' => $sink,
            // Abort before downloading anything when the declared size is already too big.
            'on_headers' => function (ResponseInterface $response) use ($limits, $sink): void {
                $length = $response->getHeaderLine('Content-Length');

                if (ctype_digit($length) && (int) $length > $limits->maxBytes) {
                    $sink->markLimitExceeded();

                    throw new ResponseTooLargeException($limits->maxBytes);
                }
            },
        ];

        $resolveEntry = $target->curlResolveEntry();

        if ($resolveEntry !== null) {
            $options['curl'] = [CURLOPT_RESOLVE => [$resolveEntry]];
        }

        return $options;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function send(ResolvedUrl $target, RequestLimits $limits, array $headers): Response
    {
        $sink = new BoundedSink($limits->maxBytes);

        try {
            $response = $this
                ->configure(Http::createPendingRequest(), $target, $limits, $sink, $headers)
                ->get($target->url->toString());
        } catch (ConnectionException|RequestException $e) {
            if ($sink->limitExceeded()) {
                throw new ResponseTooLargeException($limits->maxBytes);
            }

            throw TransferFailedException::fromClientException($e);
        }

        // The second check also covers responses that bypass the sink (e.g. faked ones in tests).
        if ($sink->limitExceeded() || strlen($response->body()) > $limits->maxBytes) {
            throw new ResponseTooLargeException($limits->maxBytes);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function flattenHeaders(Response $response): array
    {
        $headers = [];

        foreach ($response->headers() as $name => $values) {
            $headers[strtolower($name)] = (string) ($values[0] ?? '');
        }

        return $headers;
    }
}
