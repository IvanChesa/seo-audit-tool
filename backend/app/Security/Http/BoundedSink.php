<?php

namespace App\Security\Http;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

/**
 * Response body stream that refuses to grow past a byte limit.
 *
 * cURL aborts the transfer (CURLE_WRITE_ERROR) as soon as the write callback
 * reports fewer bytes than it received, so a huge or endless response never
 * gets past $maxBytes, even when the server sends no Content-Length.
 */
final class BoundedSink implements StreamInterface
{
    use StreamDecoratorTrait;

    private StreamInterface $stream;

    private int $written = 0;

    private bool $limitExceeded = false;

    public function __construct(private readonly int $maxBytes)
    {
        $this->stream = Utils::streamFor(Utils::tryFopen('php://temp', 'w+'));
    }

    public function write(string $string): int
    {
        if ($this->written + strlen($string) > $this->maxBytes) {
            $this->limitExceeded = true;

            return 0;
        }

        $this->written += strlen($string);

        return $this->stream->write($string);
    }

    public function markLimitExceeded(): void
    {
        $this->limitExceeded = true;
    }

    public function limitExceeded(): bool
    {
        return $this->limitExceeded;
    }
}
