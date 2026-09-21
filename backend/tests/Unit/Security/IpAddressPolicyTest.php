<?php

namespace Tests\Unit\Security;

use App\Security\IpAddressPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IpAddressPolicyTest extends TestCase
{
    #[DataProvider('publicAddresses')]
    public function test_public_addresses_are_allowed(string $ip): void
    {
        $this->assertTrue((new IpAddressPolicy)->isPublic($ip));
    }

    #[DataProvider('blockedAddresses')]
    public function test_non_public_addresses_are_blocked(string $ip): void
    {
        $this->assertFalse((new IpAddressPolicy)->isPublic($ip));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publicAddresses(): array
    {
        return [
            'IPv4 public' => ['93.184.216.34'],
            'IPv4 public (Cloudflare)' => ['104.16.132.229'],
            'IPv6 public (Google DNS)' => ['2001:4860:4860::8888'],
            'IPv6 public (Cloudflare)' => ['2606:4700::6810:84e5'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockedAddresses(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'loopback range' => ['127.255.255.254'],
            'unspecified' => ['0.0.0.0'],
            'private 10/8' => ['10.1.2.3'],
            'private 172.16/12 (Docker)' => ['172.17.0.1'],
            'private 192.168/16' => ['192.168.1.1'],
            'carrier-grade NAT' => ['100.64.0.1'],
            'Alibaba metadata' => ['100.100.100.200'],
            'link-local / cloud metadata' => ['169.254.169.254'],
            'Azure wireserver' => ['168.63.129.16'],
            'IETF protocol assignments' => ['192.0.0.170'],
            'TEST-NET-1' => ['192.0.2.10'],
            'benchmarking' => ['198.18.0.1'],
            'multicast' => ['224.0.0.1'],
            'reserved' => ['240.0.0.1'],
            'broadcast' => ['255.255.255.255'],
            'IPv6 loopback' => ['::1'],
            'IPv6 unspecified' => ['::'],
            'IPv4-mapped loopback' => ['::ffff:127.0.0.1'],
            'IPv4-mapped private' => ['::ffff:10.0.0.1'],
            'NAT64 of private' => ['64:ff9b::a00:1'],
            '6to4' => ['2002:7f00:1::1'],
            'Teredo' => ['2001:0:4136:e378:8000:63bf:3fff:fdd2'],
            'IPv6 documentation' => ['2001:db8::1'],
            'unique local' => ['fd12:3456:789a::1'],
            'AWS IMDS IPv6' => ['fd00:ec2::254'],
            'IPv6 link-local' => ['fe80::1'],
            'IPv6 multicast' => ['ff02::1'],
            'not an IP' => ['example.com'],
        ];
    }
}
