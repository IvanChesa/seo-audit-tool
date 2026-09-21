<?php

namespace App\Security;

/**
 * Single entry point to decide whether the server may request a URL.
 *
 * Every outgoing request (the audited page, each redirect hop, robots.txt,
 * sitemaps and every link checked) goes through inspect(), and the returned
 * addresses are the only ones the HTTP client is allowed to connect to.
 */
final class UrlGuard
{
    public function __construct(
        private readonly UrlNormalizer $normalizer,
        private readonly HostResolver $resolver,
        private readonly IpAddressPolicy $ipPolicy,
    ) {}

    /**
     * @throws UnsafeUrlException
     */
    public function inspect(string $url): ResolvedUrl
    {
        $safeUrl = $this->normalizer->normalize($url);

        if ($safeUrl->hostIsIp) {
            return new ResolvedUrl($safeUrl, [$safeUrl->host]);
        }

        $addresses = $this->resolver->resolve($safeUrl->host);

        if ($addresses === []) {
            throw new UnsafeUrlException(UrlRejection::UnresolvableHost);
        }

        // All records must be public: a host answering with one public and
        // one private address could otherwise steer the connection inside.
        foreach ($addresses as $address) {
            if (! $this->ipPolicy->isPublic($address)) {
                throw new UnsafeUrlException(UrlRejection::PrivateAddress);
            }
        }

        return new ResolvedUrl($safeUrl, $addresses);
    }
}
