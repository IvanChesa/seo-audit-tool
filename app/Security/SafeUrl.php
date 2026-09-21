<?php

namespace App\Security;

use Stringable;

/**
 * A syntactically safe, normalised http(s) URL. Instances are only created by
 * UrlNormalizer, so holding one means scheme, credentials, port and host
 * format have already been checked. It is rebuilt from its parts, which
 * removes ambiguities between PHP's URL parser and cURL's.
 */
final readonly class SafeUrl implements Stringable
{
    public function __construct(
        public string $scheme,
        public string $host,
        public int $port,
        public string $pathAndQuery,
        public bool $hostIsIp,
    ) {}

    public function isHttps(): bool
    {
        return $this->scheme === 'https';
    }

    public function authority(): string
    {
        $host = str_contains($this->host, ':') ? "[{$this->host}]" : $this->host;
        $defaultPort = $this->isHttps() ? 443 : 80;

        return $this->port === $defaultPort ? $host : "{$host}:{$this->port}";
    }

    public function origin(): string
    {
        return "{$this->scheme}://{$this->authority()}";
    }

    public function toString(): string
    {
        return $this->origin().$this->pathAndQuery;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
