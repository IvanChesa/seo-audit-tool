<?php

namespace Tests\Unit\Security;

use App\Security\UnsafeUrlException;
use App\Security\UrlGuard;
use App\Security\UrlRejection;
use Tests\TestCase;

class UrlGuardTest extends TestCase
{
    private function guard(): UrlGuard
    {
        return $this->app->make(UrlGuard::class);
    }

    private function assertRejected(string $url, UrlRejection $reason): void
    {
        try {
            $this->guard()->inspect($url);
            $this->fail("Expected {$url} to be rejected.");
        } catch (UnsafeUrlException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    public function test_it_accepts_hosts_that_resolve_to_public_addresses(): void
    {
        $resolved = $this->guard()->inspect('https://example.com/page');

        $this->assertSame('https://example.com/page', $resolved->url->toString());
        $this->assertSame([self::PUBLIC_IP], $resolved->addresses);
        $this->assertSame('example.com:443:'.self::PUBLIC_IP, $resolved->curlResolveEntry());
    }

    public function test_it_rejects_hosts_that_resolve_to_private_addresses(): void
    {
        $this->dns->set('intranet.example.com', ['10.0.0.5']);

        $this->assertRejected('https://intranet.example.com/', UrlRejection::PrivateAddress);
    }

    public function test_it_rejects_hosts_that_resolve_to_cloud_metadata(): void
    {
        $this->dns->set('metadata.attacker.com', ['169.254.169.254']);

        $this->assertRejected('http://metadata.attacker.com/latest/meta-data/', UrlRejection::PrivateAddress);
    }

    public function test_one_private_record_among_public_ones_is_enough_to_reject(): void
    {
        // Classic DNS rebinding setup: several A records, one of them internal.
        $this->dns->set('rebind.attacker.com', [self::PUBLIC_IP, '127.0.0.1']);

        $this->assertRejected('https://rebind.attacker.com/', UrlRejection::PrivateAddress);
    }

    public function test_it_rejects_ipv4_mapped_ipv6_answers(): void
    {
        $this->dns->set('mapped.attacker.com', ['::ffff:192.168.0.1']);

        $this->assertRejected('https://mapped.attacker.com/', UrlRejection::PrivateAddress);
    }

    public function test_it_rejects_hosts_that_do_not_resolve(): void
    {
        $this->assertRejected('https://does-not-exist.example.net/', UrlRejection::UnresolvableHost);
    }

    public function test_ip_literals_are_not_resolved_nor_pinned(): void
    {
        $resolved = $this->guard()->inspect('http://93.184.216.34/');

        $this->assertSame(['93.184.216.34'], $resolved->addresses);
        $this->assertNull($resolved->curlResolveEntry());
    }

    public function test_the_connection_is_pinned_to_ipv4_when_available(): void
    {
        $this->dns->set('dual.example.com', ['2606:4700::6810:84e5', self::OTHER_PUBLIC_IP]);

        $resolved = $this->guard()->inspect('https://dual.example.com:8443/');

        $this->assertSame(self::OTHER_PUBLIC_IP, $resolved->pinnedAddress());
        $this->assertSame('dual.example.com:8443:'.self::OTHER_PUBLIC_IP, $resolved->curlResolveEntry());
    }

    public function test_ipv6_only_hosts_are_pinned_with_brackets(): void
    {
        $this->dns->set('v6.example.com', ['2606:4700::6810:84e5']);

        $this->assertSame('v6.example.com:443:[2606:4700::6810:84e5]', $this->guard()->inspect('https://v6.example.com/')->curlResolveEntry());
    }
}
