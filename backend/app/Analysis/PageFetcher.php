<?php

namespace App\Analysis;

use App\Security\Http\RequestLimits;
use App\Security\Http\ResponseTooLargeException;
use App\Security\Http\SafeHttpClient;
use App\Security\Http\SafeResponse;
use App\Security\Http\TooManyRedirectsException;
use App\Security\Http\TransferFailedException;
use App\Security\UnsafeUrlException;

/**
 * Downloads the audited page and turns every possible failure into a
 * PageFetchException with a clear, user-facing reason.
 */
final class PageFetcher
{
    private const HTML_MEDIA_TYPES = ['text/html', 'application/xhtml+xml'];

    public function __construct(private readonly SafeHttpClient $client) {}

    /**
     * @throws PageFetchException
     */
    public function fetch(string $url): PageSnapshot
    {
        $response = $this->download($url);

        if ($response->status >= 500) {
            throw new PageFetchException('http_server_error', "El servidor respondió con un error HTTP {$response->status}.", retryable: true);
        }

        if (! $response->successful()) {
            throw new PageFetchException('http_error', "La página respondió con el código HTTP {$response->status}. Solo se pueden auditar páginas que respondan correctamente (2xx).");
        }

        if (! $this->isHtml($response)) {
            $type = $response->mediaType();

            throw new PageFetchException('not_html', 'La URL no devuelve una página HTML'.($type !== null ? " (tipo de contenido: {$type})" : '').'.');
        }

        return new PageSnapshot(
            requestedUrl: $url,
            finalUrl: $response->url->toString(),
            statusCode: $response->status,
            contentType: $response->header('content-type'),
            xRobotsTag: $response->header('x-robots-tag'),
            html: $response->body,
            redirects: $response->redirects,
            responseTimeMs: $response->durationMs,
        );
    }

    private function download(string $url): SafeResponse
    {
        try {
            return $this->client->get($url, RequestLimits::forPage(), [
                'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.5',
                'Accept-Language' => 'es,en;q=0.8',
            ]);
        } catch (UnsafeUrlException $e) {
            throw $e->duringRedirect
                ? new PageFetchException('unsafe_redirect', 'La página redirige a una dirección que no se puede auditar. '.$e->getMessage(), previous: $e)
                : new PageFetchException('unsafe_url', $e->getMessage(), previous: $e);
        } catch (TooManyRedirectsException $e) {
            throw new PageFetchException('too_many_redirects', $e->getMessage(), previous: $e);
        } catch (ResponseTooLargeException $e) {
            throw new PageFetchException('page_too_large', $e->getMessage(), previous: $e);
        } catch (TransferFailedException $e) {
            throw new PageFetchException($e->errorCode, $e->getMessage(), $e->retryable, $e);
        }
    }

    private function isHtml(SafeResponse $response): bool
    {
        $type = $response->mediaType();

        if ($type !== null) {
            return in_array($type, self::HTML_MEDIA_TYPES, true);
        }

        // Without Content-Type, accept only bodies that clearly are HTML documents.
        return preg_match('/^\s*(<!doctype\s+html|<html[\s>])/i', substr($response->body, 0, 1024)) === 1;
    }
}
