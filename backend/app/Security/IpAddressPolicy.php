<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Decides whether the server may open a connection to an IP address.
 *
 * Two independent checks must both pass: an explicit deny-list (readable and
 * testable) and PHP's FILTER_FLAG_GLOBAL_RANGE, which follows the IANA
 * special-purpose registries (RFC 6890).
 */
final class IpAddressPolicy
{
    /**
     * @var list<string>
     */
    public const BLOCKED_RANGES = [
        // IPv4
        '0.0.0.0/8',          // "this" network
        '10.0.0.0/8',         // private
        '100.64.0.0/10',      // carrier-grade NAT (also Alibaba Cloud metadata 100.100.100.200)
        '127.0.0.0/8',        // loopback
        '169.254.0.0/16',     // link-local (AWS/GCP/Azure/OpenStack metadata 169.254.169.254)
        '172.16.0.0/12',      // private (Docker bridge networks)
        '192.0.0.0/24',       // IETF protocol assignments
        '192.0.2.0/24',       // TEST-NET-1
        '192.88.99.0/24',     // 6to4 relay anycast
        '192.168.0.0/16',     // private
        '198.18.0.0/15',      // benchmarking
        '198.51.100.0/24',    // TEST-NET-2
        '203.0.113.0/24',     // TEST-NET-3
        '224.0.0.0/4',        // multicast
        '240.0.0.0/4',        // reserved + broadcast
        '168.63.129.16/32',   // Azure platform endpoint (public range, internal service)
        // IPv6
        '::/128',             // unspecified
        '::1/128',            // loopback
        '::/96',              // IPv4-compatible (deprecated)
        '::ffff:0:0/96',      // IPv4-mapped
        '64:ff9b::/96',       // NAT64 (embeds IPv4)
        '64:ff9b:1::/48',     // local-use NAT64
        '100::/64',           // discard-only
        '2001::/23',          // IETF protocol assignments (includes Teredo)
        '2001:db8::/32',      // documentation
        '2002::/16',          // 6to4 (embeds IPv4)
        'fc00::/7',           // unique local (AWS IMDS IPv6 fd00:ec2::254)
        'fe80::/10',          // link-local
        'fec0::/10',          // site-local (deprecated)
        'ff00::/8',           // multicast
    ];

    public function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (IpUtils::checkIp($ip, self::BLOCKED_RANGES)) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false;
    }
}
