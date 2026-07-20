<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditResult extends Model
{
    protected $fillable = [
        'audit_id',
        'type',
        'data',
        'score',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Get the audit this result belongs to.
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }
}