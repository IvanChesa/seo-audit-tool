<?php

namespace App\Analysis\Support;

use App\Security\Http\BoundedSink;
use App\Security\Http\RequestLimits;
use App\Security\Http\SafeHttpClient;
use App\Security\Http\TransferFailedException;
use App\Security\UnsafeUrlException;
use App\Security\UrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\UriResolver;

/**
 * Checks links concurrently (seo-audit.links.concurrency requests at a time).
 *
 * Links are untrusted input taken from a third-party page, so each one, and
 * each redirect it returns, is validated by the UrlGuard and pinned to its
 * validated IP, exactly like the audited page itself.
 *
 * Strategy per link: HEAD first (cheap); if the server rejects HEAD or
 * answers with an error, retry once with GET (some servers mishandle HEAD);
 * follow up to links.max_redirects redirects manually.
 */
final class LinkChecker
{
    public function __construct(
        private readonly UrlGuard $guard,
        private readonly SafeHttpClient $client,
    ) {}

    /**
     * @param  list<string>  $urls
     * @return array<string, LinkCheckResult> Keyed by the original URL, in input order.
     */
    public function check(array $urls): array
    {
        $limits = RequestLimits::forLinkCheck();
        $concurrency = max(1, (int) config('seo-audit.links.concurrency'));
        $results = [];
        $pending = [];

        foreach ($urls as $url) {
            try {
                $pending[$url] = new LinkProbe($this->guard->inspect($url));
            } catch (UnsafeUrlException) {
                $results[$url] = LinkCheckResult::skipped();
            }
        }

        while ($pending !== []) {
            $next = [];

            foreach (array_chunk($pending, $concurrency, true) as $batch) {
                $responses = Http::pool(function (Pool $pool) use ($batch, $limits): void {
                    foreach ($batch as $url => $probe) {
                        $this->client
                            ->configure($pool->as((string) $url), $probe->target, $limits, new BoundedSink($limits->maxBytes))
                            ->send($probe->method, $probe->target->url->toString());
                    }
                });

                foreach ($batch as $url => $probe) {
                    $outcome = $this->evaluate($probe, $responses[$url] ?? null, $limits);

                    if ($outcome instanceof LinkProbe) {
                        $next[$url] = $outcome;
                    } else {
                        $results[$url] = $outcome;
                    }
                }
            }

            $pending = $next;
        }

        return array_replace(array_fill_keys($urls, LinkCheckResult::skipped()), $results);
    }

    /**
     * Either the final result for the link, or the next request to make.
     */
    private function evaluate(LinkProbe $probe, mixed $response, RequestLimits $limits): LinkCheckResult|LinkProbe
    {
        if ($response instanceof ConnectionException) {
            $failure = TransferFailedException::fromClientException($response);

            return $probe->method === 'HEAD' && $failure->errorCode !== 'timeout'
                ? $probe->withMethod('GET')
                : LinkCheckResult::failed($failure->errorCode);
        }

        if (! $response instanceof Response) {
            return LinkCheckResult::failed('connection_failed');
        }

        $status = $response->status();
        $location = trim((string) $response->header('Location'));

        if (in_array($status, SafeHttpClient::REDIRECT_STATUSES, true) && $location !== '') {
            if ($probe->redirects >= $limits->maxRedirects) {
                return LinkCheckResult::failed('too_many_redirects');
            }

            try {
                return $probe->redirectTo($this->guard->inspect(UriResolver::resolve($location, $probe->target->url->toString())));
            } catch (UnsafeUrlException) {
                return LinkCheckResult::skipped();
            }
        }

        if ($status >= 400 && $probe->method === 'HEAD') {
            return $probe->withMethod('GET');
        }

        return LinkCheckResult::fromStatus($status);
    }
}
