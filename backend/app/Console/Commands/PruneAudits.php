<?php

namespace App\Console\Commands;

use App\Enums\AuditStatus;
use App\Models\Audit;
use App\Models\Exceptions\InvalidStatusTransition;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Housekeeping, scheduled hourly (routes/console.php):
 *  - audits stuck in pending/processing (worker crash, lost job) are marked
 *    as failed so the UI never polls forever;
 *  - when a retention period is configured (opt-in), older audits are
 *    deleted with their results.
 */
class PruneAudits extends Command
{
    protected $signature = 'audits:prune
        {--days= : Delete audits older than this many days (default: AUDIT_RETENTION_DAYS, 0 keeps them)}
        {--stale-minutes= : Fail audits unfinished after this many minutes (default: AUDIT_STALE_AFTER_MINUTES)}';

    protected $description = 'Fail stuck audits and delete audits older than the retention period';

    public function handle(): int
    {
        $staleMinutes = (int) ($this->option('stale-minutes') ?? config('seo-audit.retention.stale_after_minutes'));
        $retentionDays = (int) ($this->option('days') ?? config('seo-audit.retention.days'));

        $failed = $this->failStaleAudits($staleMinutes);
        $deleted = $retentionDays > 0 ? $this->deleteOldAudits($retentionDays) : 0;

        $this->components->info("Stale audits marked as failed: {$failed}. Old audits deleted: {$deleted}.");

        return self::SUCCESS;
    }

    private function failStaleAudits(int $minutes): int
    {
        $count = 0;

        Audit::query()
            ->whereIn('status', [AuditStatus::Pending->value, AuditStatus::Processing->value])
            ->where('created_at', '<', now()->subMinutes($minutes))
            ->each(function (Audit $audit) use (&$count): void {
                try {
                    $audit->markFailed('timeout', 'El análisis no terminó en el tiempo esperado. Repite la auditoría.');
                    $count++;
                } catch (InvalidStatusTransition) {
                    // A worker finished it between the query and the update: nothing to do.
                }
            });

        return $count;
    }

    private function deleteOldAudits(int $days): int
    {
        $count = 0;

        Audit::query()
            ->where('created_at', '<', now()->subDays($days))
            ->chunkById(200, function (Collection $audits) use (&$count): void {
                // Results and broken links are removed by ON DELETE CASCADE.
                $count += Audit::query()->whereKey($audits->modelKeys())->delete();
            });

        return $count;
    }
}
