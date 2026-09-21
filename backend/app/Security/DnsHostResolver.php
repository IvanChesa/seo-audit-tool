<?php

namespace App\Security;

use ErrorException;

final class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        // The trailing dot makes the name fully qualified, so the resolver
        // never appends local search domains (e.g. "*.svc.cluster.local").
        $fqdn = $host.'.';
        $addresses = [];

        foreach ([DNS_A => 'ip', DNS_AAAA => 'ipv6'] as $type => $field) {
            foreach ($this->records($fqdn, $type) as $record) {
                if (isset($record[$field]) && is_string($record[$field])) {
                    $addresses[] = $record[$field];
                }
            }
        }

        if ($addresses === []) {
            $addresses = gethostbynamel($fqdn) ?: [];
        }

        return array_values(array_unique($addresses));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function records(string $fqdn, int $type): array
    {
        try {
            $records = dns_get_record($fqdn, $type);
        } catch (ErrorException) {
            // Laravel turns the DNS warning into an ErrorException; for us a
            // failed lookup simply means "no addresses".
            return [];
        }

        return $records === false ? [] : $records;
    }
}
