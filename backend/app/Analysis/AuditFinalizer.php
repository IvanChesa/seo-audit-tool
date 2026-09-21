<?php

namespace App\Analysis;

use App\Enums\AuditStatus;
use App\Enums\Section;
use App\Enums\SectionStatus;
use App\Models\Audit;
use App\Models\AuditResult;
use Illuminate\Support\Facades\DB;

/**
 * Runs once every analyzer job of an audit has finished (successfully or
 * not): fills in sections that never reported, computes the global score and
 * closes the audit. Safe to call more than once.
 */
final class AuditFinalizer
{
    public function __construct(
        private readonly SectionRecorder $recorder,
        private readonly SnapshotStore $snapshots,
    ) {}

    public function finalize(int $auditId): void
    {
        DB::transaction(function () use ($auditId): void {
            // Row lock: two finalizers for the same audit run one after the other.
            $audit = Audit::query()->lockForUpdate()->find($auditId);

            if ($audit === null || $audit->status !== AuditStatus::Processing) {
                return;
            }

            $recorded = $audit->results()->pluck('type')->all();

            foreach (Section::cases() as $section) {
                if (! in_array($section->value, $recorded, true)) {
                    $this->recorder->recordFailure($audit, $section, 'analysis_incomplete', 'Este análisis no llegó a completarse.');
                }
            }

            $completed = $audit->results()
                ->where('status', SectionStatus::Completed->value)
                ->get()
                ->filter(fn (AuditResult $result) => $result->section() !== null && $result->score !== null);

            $score = ScoreCalculator::globalScore(
                $completed->mapWithKeys(fn (AuditResult $result) => [$result->type => (int) $result->score])->all(),
                $completed->contains(fn (AuditResult $result) => ScoreCalculator::containsCritical($result->issues ?? [])),
            );

            if ($score === null) {
                $audit->markFailed('analysis_failed', 'No se pudo completar ninguno de los análisis de la página.');
            } else {
                $audit->markCompleted($score);
            }
        });

        $this->snapshots->forget($auditId);
    }
}
