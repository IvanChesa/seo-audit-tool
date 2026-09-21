<?php

namespace App\Models;

use App\Enums\Section;
use App\Enums\SectionStatus;
use Database\Factories\AuditResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Result of one report section. There is at most one row per (audit, type).
 *
 * @property int $id
 * @property int $audit_id
 * @property string $type
 * @property SectionStatus $status
 * @property int|null $score
 * @property array<string, mixed> $data
 * @property list<array<string, mixed>>|null $issues
 * @property string|null $error_code
 * @property string|null $error_message
 */
class AuditResult extends Model
{
    /** @use HasFactory<AuditResultFactory> */
    use HasFactory;

    protected $fillable = [
        'audit_id',
        'type',
        'status',
        'score',
        'data',
        'issues',
        'error_code',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => SectionStatus::class,
            'score' => 'integer',
            'data' => 'array',
            'issues' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Audit, $this>
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /**
     * Rows written by older versions may use types that no longer exist.
     */
    public function section(): ?Section
    {
        return Section::tryFrom($this->type);
    }
}
