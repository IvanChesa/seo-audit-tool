<?php

namespace App\Security;

interface HostResolver
{
    /**
     * Returns every IPv4/IPv6 address the hostname resolves to, or an empty
     * list when it cannot be resolved.
     *
     * @return list<string>
     */
    public function resolve(string $host): array;
}
