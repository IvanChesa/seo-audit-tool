<?php

namespace App\Jobs;

use App\Analysis\AuditFinalizer;
use App\Analysis\PageFetcher;
use App\Analysis\PageFetchException;
use App\Analysis\SnapshotStore;
use App\Enums\AuditStatus;
use App\Enums\Section;
use App\Models\Audit;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * First step of an audit: download the page (through the SSRF-safe client),
 * keep it in the cache and fan out one analyzer job per report section.
 *
 * Retries: transient network errors (timeouts, connection resets, 5xx) are
 * retried with a delay; permanent ones (404, not HTML, unsafe redirect...)
 * fail the audit immediately with a user-facing reason.
 */
class FetchPageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> Seconds to wait before the 2nd and 3rd attempts. */
    public array $backoff = [10, 30];

    /** Page download (with redirects) is bounded well below this. */
    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $auditId) {}

    public function handle(PageFetcher $fetcher, SnapshotStore $snapshots): void
    {
        $audit = Audit::query()->find($this->auditId);

        // Deleted meanwhile, or already finished by a previous attempt.
        if ($audit === null || $audit->status->isFinished()) {
            return;
        }

        if ($audit->status === AuditStatus::Pending) {
            $audit->markProcessing();
        }

        try {
            $snapshot = $fetcher->fetch($audit->url);
        } catch (PageFetchException $e) {
            $this->handleFetchFailure($audit, $e);

            return;
        }

        $audit->forceFill([
            'final_url' => $snapshot->finalUrl,
            'http_status' => $snapshot->statusCode,
        ])->save();

        $snapshots->put($audit->id, $snapshot);

        $this->dispatchAnalyzers($audit->id);
    }

    /**
     * Called by the queue after the last attempt threw or timed out. The audit
     * must never stay "processing" forever.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Audit page fetch crashed', [
            'audit_id' => $this->auditId,
            'exception' => $exception !== null ? $exception::class : null,
            'message' => $exception?->getMessage(),
        ]);

        $audit = Audit::query()->find($this->auditId);

        if ($audit !== null && ! $audit->status->isFinished()) {
            $audit->markFailed('unexpected_error', 'Se produjo un error inesperado al descargar la página. Inténtalo de nuevo más tarde.');
        }
    }

    private function handleFetchFailure(Audit $audit, PageFetchException $e): void
    {
        $context = [
            'audit_id' => $audit->id,
            'reason' => $e->errorCode,
            'attempt' => $this->attempts(),
            'detail' => $e->getPrevious()?->getMessage(),
        ];

        if ($e->retryable && $this->attempts() < $this->tries) {
            Log::info('Transient page fetch failure, retrying', $context);
            $this->release($this->backoff[$this->attempts() - 1] ?? 30);

            return;
        }

        Log::notice('Audit page could not be fetched', $context);
        $audit->markFailed($e->errorCode, $e->getMessage());
    }

    private function dispatchAnalyzers(int $auditId): void
    {
        $jobs = array_map(
            fn (Section $section) => new RunAnalyzerJob($auditId, $section),
            Section::cases(),
        );

        // allowFailures(): one failing section must not cancel the others;
        // the finalizer marks it as failed and excludes it from the score.
        Bus::batch($jobs)
            ->name("audit:{$auditId}")
            ->allowFailures()
            ->finally(static function (Batch $batch) use ($auditId): void {
                app(AuditFinalizer::class)->finalize($auditId);
            })
            ->dispatch();
    }
}
