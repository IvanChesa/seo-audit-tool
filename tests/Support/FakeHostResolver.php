<?php

namespace Tests\Support;

use App\Security\HostResolver;

/**
 * Deterministic DNS for tests: no real lookups, and hostile answers (private
 * IPs, rebinding) can be simulated per host.
 */
final class FakeHostResolver implements HostResolver
{
    /**
     * @param  array<string, list<string>>  $records
     */
    public function __construct(private array $records = []) {}

    /**
     * @param  list<string>  $addresses
     */
    public function set(string $host, array $addresses): self
    {
        $this->records[strtolower($host)] = $addresses;

        return $this;
    }

    public function resolve(string $host): array
    {
        return $this->records[strtolower($host)] ?? [];
    }
}
