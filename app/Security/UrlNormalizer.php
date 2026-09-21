<?php

namespace App\Security;

use Illuminate\Container\Attributes\Config;

/**
 * Parses and normalises a URL, rejecting anything the server must never
 * request. It does not touch the network: DNS checks live in UrlGuard.
 */
final class UrlNormalizer
{
    public const MAX_LENGTH = 2048;

    /**
     * Special-use or private suffixes (RFC 6761, RFC 8375, ICANN .internal).
     */
    private const LOCAL_SUFFIXES = ['localhost', 'local', 'localdomain', 'internal', 'home.arpa'];

    /**
     * Cloud metadata hostnames that are not covered by the suffixes above.
     */
    private const BLOCKED_HOSTNAMES = ['metadata.goog'];

    /**
     * @param  list<int>  $allowedPorts
     */
    public function __construct(
        private readonly IpAddressPolicy $ipPolicy,
        #[Config('seo-audit.allowed_ports')]
        private readonly array $allowedPorts,
    ) {}

    /**
     * Adds https:// when the user typed a bare domain such as "example.com"
     * or "example.com:8080". Other schemes ("mailto:", "javascript:") are left
     * untouched so they are rejected with a meaningful message.
     */
    public static function withDefaultScheme(string $input): string
    {
        $input = trim($input);

        return $input !== '' && ! str_contains($input, '://') && ! preg_match('/^[a-z][a-z0-9+.-]*:(?!\d)/i', $input)
            ? 'https://'.$input
            : $input;
    }

    /**
     * @throws UnsafeUrlException
     */
    public function normalize(string $input): SafeUrl
    {
        $url = trim($input);

        if ($url === '') {
            throw new UnsafeUrlException(UrlRejection::Empty);
        }

        if (strlen($url) > self::MAX_LENGTH) {
            throw new UnsafeUrlException(UrlRejection::TooLong);
        }

        // Whitespace, control characters and backslashes are never valid in a
        // URL and are the usual trick to make two URL parsers disagree.
        if (preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            throw new UnsafeUrlException(UrlRejection::InvalidFormat);
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'])) {
            throw new UnsafeUrlException(UrlRejection::InvalidFormat);
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new UnsafeUrlException(UrlRejection::UnsupportedScheme);
        }

        if (isset($parts['user']) || isset($parts['pass']) || str_contains($this->authorityOf($url), '@')) {
            throw new UnsafeUrlException(UrlRejection::EmbeddedCredentials);
        }

        if (! isset($parts['host']) || $parts['host'] === '') {
            throw new UnsafeUrlException(UrlRejection::InvalidFormat);
        }

        [$host, $hostIsIp] = $this->normalizeHost($parts['host']);

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (! in_array($port, $this->allowedPorts, true)) {
            throw new UnsafeUrlException(UrlRejection::DisallowedPort);
        }

        $path = ($parts['path'] ?? '') === '' ? '/' : $parts['path'];
        $query = ($parts['query'] ?? '') === '' ? '' : '?'.$parts['query'];

        return new SafeUrl($scheme, $host, $port, $this->encodeNonAscii($path.$query), $hostIsIp);
    }

    /**
     * @return array{0: string, 1: bool} [normalised host, host is an IP literal]
     */
    private function normalizeHost(string $rawHost): array
    {
        if (str_starts_with($rawHost, '[')) {
            return [$this->normalizeIpv6Literal($rawHost), true];
        }

        $host = rtrim(strtolower($rawHost), '.');

        if ($host === '' || str_contains($host, '%')) {
            throw new UnsafeUrlException(UrlRejection::InvalidHost);
        }

        if (preg_match('/[^\x21-\x7E]/', $host) === 1) {
            $host = $this->toAscii($host);
        }

        // A numeric last label means an IPv4 address. Only the canonical
        // dotted-quad form is accepted: "127.1", "2130706433", "0x7f.0.0.1"
        // or "0177.0.0.1" are interpreted as loopback by some resolvers.
        if (preg_match('/(^|\.)(0x[0-9a-f]*|[0-9]+)$/', $host) === 1) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                throw new UnsafeUrlException(UrlRejection::InvalidHost);
            }

            $this->assertPublicIp($host);

            return [$host, true];
        }

        if ($this->isLocalHostname($host)) {
            throw new UnsafeUrlException(UrlRejection::LocalHostname);
        }

        if (! $this->isValidHostname($host)) {
            throw new UnsafeUrlException(UrlRejection::InvalidHost);
        }

        return [$host, false];
    }

    private function normalizeIpv6Literal(string $rawHost): string
    {
        $ip = substr($rawHost, 1, -1);

        if (! str_ends_with($rawHost, ']') || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            throw new UnsafeUrlException(UrlRejection::InvalidHost);
        }

        $packed = inet_pton($ip);
        $canonical = $packed === false ? false : inet_ntop($packed);

        if ($canonical === false) {
            throw new UnsafeUrlException(UrlRejection::InvalidHost);
        }

        $this->assertPublicIp($canonical);

        return $canonical;
    }

    private function assertPublicIp(string $ip): void
    {
        if (! $this->ipPolicy->isPublic($ip)) {
            throw new UnsafeUrlException(UrlRejection::PrivateAddress);
        }
    }

    private function toAscii(string $host): string
    {
        if (! function_exists('idn_to_ascii')) {
            throw new UnsafeUrlException(UrlRejection::InvalidHost);
        }

        $ascii = idn_to_ascii($host, IDNA_DEFAULT | IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

        if ($ascii === false) {
            throw new UnsafeUrlException(UrlRejection::InvalidHost);
        }

        return strtolower($ascii);
    }

    private function isLocalHostname(string $host): bool
    {
        // Single-label names ("intranet", "mysql", "metadata") only resolve
        // inside private networks or through local search domains.
        if (! str_contains($host, '.') || in_array($host, self::BLOCKED_HOSTNAMES, true)) {
            return true;
        }

        foreach (self::LOCAL_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    private function isValidHostname(string $host): bool
    {
        if (strlen($host) > 253) {
            return false;
        }

        foreach (explode('.', $host) as $label) {
            if (strlen($label) > 63 || preg_match('/^[a-z0-9_](?:[a-z0-9_-]*[a-z0-9_])?$/', $label) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * The authority is what sits between "scheme://" and the first "/", "?" or "#".
     */
    private function authorityOf(string $url): string
    {
        $afterScheme = substr($url, (int) strpos($url, '://') + 3);

        return (string) strtok($afterScheme, '/?#');
    }

    /**
     * Percent-encodes raw non-ASCII bytes in the path and query (e.g. "/café").
     */
    private function encodeNonAscii(string $value): string
    {
        return (string) preg_replace_callback(
            '/[^\x21-\x7E]/',
            fn (array $match): string => rawurlencode($match[0]),
            $value,
        );
    }
}
