<?php

namespace App\Models;

use App\Enums\AuditStatus;
use App\Models\Exceptions\InvalidStatusTransition;
use App\Security\SafeUrl;
use Database\Factories\AuditFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $url
 * @property string|null $host
 * @property string|null $final_url
 * @property AuditStatus $status
 * @property int|null $score
 * @property int|null $http_status
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int|null $report_version
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Audit extends Model
{
    /** @use HasFactory<AuditFactory> */
    use HasFactory;

    /**
     * Version of the structured report produced by the current analyzers.
     * Audits stored with an older (or no) version are shown as legacy.
     */
    public const REPORT_VERSION = 2;

    protected $fillable = [
        'url',
        'host',
        'status',
        'report_version',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => AuditStatus::class,
            'score' => 'integer',
            'http_status' => 'integer',
            'report_version' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public static function createFor(SafeUrl $url): self
    {
        return self::create([
            'url' => $url->toString(),
            'host' => $url->host,
            'status' => AuditStatus::Pending,
            'report_version' => self::REPORT_VERSION,
        ]);
    }

    /**
     * @return HasMany<AuditResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(AuditResult::class);
    }

    /**
     * @return HasMany<BrokenLink, $this>
     */
    public function brokenLinks(): HasMany
    {
        return $this->hasMany(BrokenLink::class);
    }

    /**
     * @param  Builder<Audit>  $query
     */
    #[Scope]
    protected function filter(Builder $query, ?AuditStatus $status = null, ?string $search = null): void
    {
        $query
            ->when($status, fn (Builder $q) => $q->where('status', $status?->value))
            ->when($search, fn (Builder $q) => $q->where('url', 'like', '%'.addcslashes((string) $search, '%_\\').'%'));
    }

    public function isLegacy(): bool
    {
        return $this->report_version !== self::REPORT_VERSION;
    }

    public function markProcessing(): void
    {
        $this->transitionTo(AuditStatus::Processing, ['started_at' => now()]);
    }

    public function markCompleted(int $score): void
    {
        $this->transitionTo(AuditStatus::Completed, [
            'score' => max(0, min(100, $score)),
            'finished_at' => now(),
        ]);
    }

    /**
     * @param  string  $message  User-facing explanation (never an exception message).
     */
    public function markFailed(string $code, string $message): void
    {
        $this->transitionTo(AuditStatus::Failed, [
            'error_code' => Str::limit($code, 60, ''),
            'error_message' => Str::limit($message, 250),
            'finished_at' => now(),
        ]);
    }

    /**
     * Compare-and-set in a single UPDATE: the row only changes if its current
     * status allows the transition, so concurrent workers cannot both finish
     * (or re-open) the same audit.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidStatusTransition
     */
    public function transitionTo(AuditStatus $next, array $attributes = []): void
    {
        $sources = array_map(fn (AuditStatus $status) => $status->value, AuditStatus::allowedSourcesFor($next));

        $updated = static::query()
            ->whereKey($this->getKey())
            ->whereIn('status', $sources)
            ->update(['status' => $next->value, ...$attributes]);

        if ($updated === 0) {
            $current = static::query()->whereKey($this->getKey())->value('status');

            throw InvalidStatusTransition::between(
                is_string($current) ? AuditStatus::from($current) : null,
                $next,
                (int) $this->getKey(),
            );
        }

        $this->refresh();
    }
}
