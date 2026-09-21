<?php

namespace App\Jobs;

use App\Analysis\AuditContext;
use App\Analysis\Contracts\Analyzer;
use App\Analysis\SectionRecorder;
use App\Analysis\SnapshotStore;
use App\Enums\Section;
use App\Models\Audit;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the analyzer of one report section. All sections of an audit run in
 * parallel inside a batch dispatched by FetchPageJob.
 *
 * Results are upserted, so a retried job replaces its previous result. If
 * every attempt fails, failed() records the section as "failed" and the
 * audit still completes with the remaining sections.
 */
class RunAnalyzerJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [15];

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $auditId,
        public readonly Section $section,
    ) {
        $this->timeout = $section->timeout();
    }

    public function handle(SnapshotStore $snapshots, SectionRecorder $recorder, Container $container): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $audit = Audit::query()->find($this->auditId);

        if ($audit === null || $audit->status->isFinished()) {
            return;
        }

        $snapshot = $snapshots->get($audit->id);

        if ($snapshot === null) {
            $recorder->recordFailure($audit, $this->section, 'snapshot_expired', 'Los datos descargados de la página caducaron antes de poder analizarlos. Repite la auditoría.');

            return;
        }

        /** @var Analyzer $analyzer */
        $analyzer = $container->make($this->section->analyzer());

        $recorder->record($audit, $this->section, $analyzer->analyze(new AuditContext($audit->id, $snapshot)));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Audit section analysis failed', [
            'audit_id' => $this->auditId,
            'section' => $this->section->value,
            'exception' => $exception !== null ? $exception::class : null,
            'message' => $exception?->getMessage(),
        ]);

        $audit = Audit::query()->find($this->auditId);

        if ($audit !== null) {
            app(SectionRecorder::class)->recordFailure(
                $audit,
                $this->section,
                'analysis_failed',
                "No se pudo completar el análisis de «{$this->section->label()}».",
            );
        }
    }
}
