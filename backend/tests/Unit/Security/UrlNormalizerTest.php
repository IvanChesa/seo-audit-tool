<?php

namespace Tests\Unit\Security;

use App\Security\UnsafeUrlException;
use App\Security\UrlNormalizer;
use App\Security\UrlRejection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UrlNormalizerTest extends TestCase
{
    private UrlNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = $this->app->make(UrlNormalizer::class);
    }

    #[DataProvider('validUrls')]
    public function test_it_normalizes_valid_urls(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($input)->toString());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validUrls(): array
    {
        return [
            'adds root path' => ['https://example.com', 'https://example.com/'],
            'lower-cases scheme and host' => ['HTTPS://Example.COM/Path', 'https://example.com/Path'],
            'removes default port' => ['http://example.com:80/a', 'http://example.com/a'],
            'keeps allowed custom port' => ['https://example.com:8443/', 'https://example.com:8443/'],
            'removes fragment' => ['https://example.com/page?x=1#section', 'https://example.com/page?x=1'],
            'strips trailing dot of FQDN' => ['https://example.com./', 'https://example.com/'],
            'trims whitespace around' => ['  https://example.com/  ', 'https://example.com/'],
            'converts IDN to punycode' => ['https://españa.example.com/', 'https://xn--espaa-rta.example.com/'],
            'percent-encodes non-ASCII path' => ['https://example.com/café', 'https://example.com/caf%C3%A9'],
            'public IPv4 literal' => ['http://93.184.216.34/', 'http://93.184.216.34/'],
            'public IPv6 literal' => ['http://[2001:4860:4860::8888]/', 'http://[2001:4860:4860::8888]/'],
        ];
    }

    #[DataProvider('rejectedUrls')]
    public function test_it_rejects_dangerous_or_invalid_urls(string $input, UrlRejection $reason): void
    {
        try {
            $this->normalizer->normalize($input);
            $this->fail("Expected {$input} to be rejected.");
        } catch (UnsafeUrlException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    /**
     * @return array<string, array{string, UrlRejection}>
     */
    public static function rejectedUrls(): array
    {
        return [
            'empty' => ['', UrlRejection::Empty],
            'too long' => ['https://example.com/'.str_repeat('a', 2100), UrlRejection::TooLong],
            'not a URL' => ['esto no es una url', UrlRejection::InvalidFormat],
            'ftp' => ['ftp://example.com/file', UrlRejection::UnsupportedScheme],
            'file' => ['file:///etc/passwd', UrlRejection::UnsupportedScheme],
            'gopher' => ['gopher://example.com:70/', UrlRejection::UnsupportedScheme],
            'javascript' => ['javascript:alert(1)', UrlRejection::UnsupportedScheme],
            'data' => ['data:text/html,<script>alert(1)</script>', UrlRejection::UnsupportedScheme],
            'user and password' => ['https://user:pass@example.com/', UrlRejection::EmbeddedCredentials],
            'user only' => ['https://admin@example.com/', UrlRejection::EmbeddedCredentials],
            'credentials hiding the real host' => ['https://example.com@127.0.0.1/', UrlRejection::EmbeddedCredentials],
            'backslash parser confusion' => ['https://example.com\@127.0.0.1/', UrlRejection::InvalidFormat],
            'embedded whitespace' => ['https://exa mple.com/', UrlRejection::InvalidFormat],
            'NUL byte' => ["https://exam\x00ple.com/", UrlRejection::InvalidFormat],
            'tab inside' => ["https://example.com/a\tb", UrlRejection::InvalidFormat],
            'SSH port' => ['http://example.com:22/', UrlRejection::DisallowedPort],
            'Redis port' => ['http://example.com:6379/', UrlRejection::DisallowedPort],
            'MySQL port' => ['http://example.com:3306/', UrlRejection::DisallowedPort],
            'localhost' => ['http://localhost/', UrlRejection::LocalHostname],
            'localhost upper-case with dot' => ['http://LOCALHOST./', UrlRejection::LocalHostname],
            'localhost subdomain' => ['http://api.localhost/', UrlRejection::LocalHostname],
            'mDNS .local' => ['http://printer.local/', UrlRejection::LocalHostname],
            'GCP metadata host' => ['http://metadata.google.internal/', UrlRejection::LocalHostname],
            'GCP metadata alias' => ['http://metadata.goog/', UrlRejection::LocalHostname],
            'single-label host (Docker service)' => ['http://mysql/', UrlRejection::LocalHostname],
            'home.arpa' => ['http://router.home.arpa/', UrlRejection::LocalHostname],
            'loopback IPv4' => ['http://127.0.0.1/', UrlRejection::PrivateAddress],
            'private IPv4' => ['http://192.168.1.1/admin', UrlRejection::PrivateAddress],
            'cloud metadata IPv4' => ['http://169.254.169.254/latest/meta-data/', UrlRejection::PrivateAddress],
            'unspecified IPv4' => ['http://0.0.0.0/', UrlRejection::PrivateAddress],
            'loopback IPv6' => ['http://[::1]/', UrlRejection::PrivateAddress],
            'IPv4-mapped IPv6' => ['http://[::ffff:127.0.0.1]/', UrlRejection::PrivateAddress],
            'link-local IPv6' => ['http://[fe80::1]/', UrlRejection::PrivateAddress],
            'AWS IMDS IPv6' => ['http://[fd00:ec2::254]/', UrlRejection::PrivateAddress],
            'short IPv4 form' => ['http://127.1/', UrlRejection::InvalidHost],
            'decimal IPv4 form' => ['http://2130706433/', UrlRejection::InvalidHost],
            'hex IPv4 form' => ['http://0x7f000001/', UrlRejection::InvalidHost],
            'octal IPv4 form' => ['http://0177.0.0.1/', UrlRejection::InvalidHost],
            'mixed hex dotted form' => ['http://0x7f.0.0.1/', UrlRejection::InvalidHost],
            'invalid IPv6 literal' => ['http://[::zz]/', UrlRejection::InvalidHost],
            'percent-encoded host' => ['http://%31%32%37.0.0.1/', UrlRejection::InvalidHost],
            'invalid label' => ['https://-example.com/', UrlRejection::InvalidHost],
        ];
    }

    #[DataProvider('schemeCompletion')]
    public function test_it_adds_https_to_bare_domains(string $input, string $expected): void
    {
        $this->assertSame($expected, UrlNormalizer::withDefaultScheme($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function schemeCompletion(): array
    {
        return [
            'bare domain' => ['example.com', 'https://example.com'],
            'domain with port' => ['example.com:8080/path', 'https://example.com:8080/path'],
            'already http' => ['http://example.com', 'http://example.com'],
            'other scheme left untouched' => ['javascript:alert(1)', 'javascript:alert(1)'],
            'mailto left untouched' => ['mailto:hola@example.com', 'mailto:hola@example.com'],
            'empty stays empty' => ['  ', ''],
        ];
    }
}
