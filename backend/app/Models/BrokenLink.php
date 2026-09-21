<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $audit_id
 * @property string $url
 * @property bool $is_internal
 * @property int|null $status_code
 * @property string|null $error
 * @property string|null $link_text
 */
class BrokenLink extends Model
{
    protected $fillable = [
        'audit_id',
        'url',
        'is_internal',
        'status_code',
        'error',
        'link_text',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'status_code' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Audit, $this>
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }
}
