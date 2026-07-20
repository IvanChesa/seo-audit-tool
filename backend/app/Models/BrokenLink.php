<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrokenLink extends Model
{
    protected $fillable = [
        'audit_id',
        'url',
        'status_code',
        'link_text',
    ];

    /**
     * Get the audit this broken link belongs to.
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }
}