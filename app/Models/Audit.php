<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Audit extends Model
{
    protected $fillable = [
        'url',
        'status',
        'score',
    ];

    /**
     * Get all results for this audit.
     */
    public function results(): HasMany
    {
        return $this->hasMany(AuditResult::class);
    }

    /**
     * Get all broken links found in this audit.
     */
    public function brokenLinks(): HasMany
    {
        return $this->hasMany(BrokenLink::class);
    }
}