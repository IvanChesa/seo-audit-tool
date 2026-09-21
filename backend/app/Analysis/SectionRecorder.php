<?php

namespace App\Analysis;

use App\Enums\Section;
use App\Enums\SectionStatus;
use App\Models\Audit;
use App\Models\AuditResult;
use Illuminate\Support\Facades\DB;

/**
 * Persists section results. Writes are idempotent: the unique
 * (audit_id, type) index plus upserts mean a retried job replaces its
 * previous result instead of duplicating it.
 */
final class SectionRecorder
{
    public function record(Audit $audit, Section $section, SectionResult $result): void
    {
        DB::transaction(function () use ($audit, $section, $result): void {
            AuditResult::query()->updateOrCreate(
                ['audit_id' => $audit->id, 'type' => $section->value],
                [
                    'status' => $result->status,
                    'score' => $result->score,
                    'data' => $result->data,
                    'issues' => $result->issuesToArray(),
                    'error_code' => $result->errorCode,
                    'error_message' => $result->errorMessage,
                ],
            );

            if ($section === Section::Links) {
                $audit->brokenLinks()->delete();
                $audit->brokenLinks()->createMany($result->brokenLinks);
            }
        });
    }

    /**
     * Records that a section could not be completed, unless it already has a
     * result: a late failure notification must never overwrite a good result.
     */
    public function recordFailure(Audit $audit, Section $section, string $code, string $message): void
    {
        AuditResult::query()->createOrFirst(
            ['audit_id' => $audit->id, 'type' => $section->value],
            [
                'status' => SectionStatus::Failed,
                'score' => null,
                'data' => [],
                'issues' => [],
                'error_code' => $code,
                'error_message' => $message,
            ],
        );
    }
}
