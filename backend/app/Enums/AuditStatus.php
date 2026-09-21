<?php

namespace App\Enums;

/**
 * Lifecycle of an audit:
 *
 *   pending ──▶ processing ──▶ completed
 *      │             │
 *      └─────────────┴──────▶ failed
 *
 * completed and failed are terminal: a finished audit is never re-opened
 * (repeating an audit creates a new one, which keeps the history intact).
 */
enum AuditStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Processing, self::Failed], true),
            self::Processing => in_array($next, [self::Completed, self::Failed], true),
            self::Completed, self::Failed => false,
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }

    /**
     * Statuses from which $next can be reached.
     *
     * @return list<self>
     */
    public static function allowedSourcesFor(self $next): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status): bool => $status->canTransitionTo($next),
        ));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
