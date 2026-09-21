<?php

namespace App\Http\Resources;

use App\Analysis\AuditReport;
use App\Models\Audit;
use Illuminate\Http\Request;

/**
 * Full audit with its report. Audits created by an older version of the
 * analyzers are flagged as legacy and returned without report sections,
 * because their stored data does not follow the current structure.
 *
 * @mixin Audit
 */
class AuditResource extends AuditSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Audit $audit */
        $audit = $this->resource;
        $audit->loadMissing(['results', 'brokenLinks']);
        $legacy = $audit->isLegacy();
        $report = new AuditReport($audit);

        return [
            ...parent::toArray($request),
            'http_status' => $this->http_status,
            'started_at' => $this->started_at?->toIso8601String(),
            'legacy' => $legacy,
            'progress' => $legacy ? null : $report->progress(),
            'score_breakdown' => $legacy ? null : $report->scoreBreakdown(),
            'issues_summary' => $legacy ? null : $report->issuesSummary(),
            'issues' => $legacy ? [] : $report->issues(),
            'sections' => $legacy ? [] : $report->sections(),
        ];
    }
}
