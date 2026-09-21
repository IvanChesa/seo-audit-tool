<?php

namespace App\Security;

/**
 * A SafeUrl whose host has been resolved and whose addresses are all public.
 */
final readonly class ResolvedUrl
{
    /**
     * @param  non-empty-list<string>  $addresses
     */
    public function __construct(
        public SafeUrl $url,
        public array $addresses,
    ) {}

    /**
     * The address the connection is pinned to. IPv4 is preferred because many
     * container networks have no IPv6 route.
     */
    public function pinnedAddress(): string
    {
        foreach ($this->addresses as $address) {
            if (! str_contains($address, ':')) {
                return $address;
            }
        }

        return $this->addresses[0];
    }

    /**
     * Entry for CURLOPT_RESOLVE. It makes cURL connect to the address that was
     * validated instead of resolving the hostname a second time, which is what
     * a DNS rebinding attack relies on. IP literals need no pinning.
     */
    public function curlResolveEntry(): ?string
    {
        if ($this->url->hostIsIp) {
            return null;
        }

        $address = $this->pinnedAddress();
        $address = str_contains($address, ':') ? "[{$address}]" : $address;

        return "{$this->url->host}:{$this->url->port}:{$address}";
    }
}
