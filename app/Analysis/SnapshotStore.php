<?php

namespace App\Analysis;

use Illuminate\Contracts\Cache\Repository;

/**
 * Temporary storage of the downloaded page while its analyzers run.
 * Entries expire on their own (seo-audit.snapshot_ttl_minutes) and are
 * removed explicitly as soon as the audit is finalised or deleted.
 */
final class SnapshotStore
{
    public function __construct(private readonly Repository $cache) {}

    public function put(int $auditId, PageSnapshot $snapshot): void
    {
        $this->cache->put(
            $this->key($auditId),
            $snapshot->toArray(),
            now()->addMinutes((int) config('seo-audit.snapshot_ttl_minutes')),
        );
    }

    public function get(int $auditId): ?PageSnapshot
    {
        $data = $this->cache->get($this->key($auditId));

        return is_array($data) ? PageSnapshot::fromArray($data) : null;
    }

    public function forget(int $auditId): void
    {
        $this->cache->forget($this->key($auditId));
    }

    private function key(int $auditId): string
    {
        return "audit:{$auditId}:snapshot";
    }
}
