<?php

namespace App\Http\Resources;

use App\Analysis\ScoreCalculator;
use App\Models\Audit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact representation used by the history list.
 *
 * @mixin Audit
 */
class AuditSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'host' => $this->host,
            'final_url' => $this->final_url,
            'status' => $this->status->value,
            'score' => $this->score,
            'score_rating' => ScoreCalculator::rating($this->score),
            'error' => $this->error_code === null ? null : [
                'code' => $this->error_code,
                'message' => $this->error_message,
            ],
            'created_at' => $this->created_at->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
