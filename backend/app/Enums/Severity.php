<?php

namespace App\Enums;

/**
 * How serious a detected problem is. The penalty is subtracted from the
 * section score (starting at 100), so a single critical issue weighs as much
 * as eight low ones.
 */
enum Severity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function penalty(): int
    {
        return match ($this) {
            self::Critical => 40,
            self::High => 20,
            self::Medium => 10,
            self::Low => 5,
        };
    }

    /**
     * Sort order: lower rank = more urgent.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::High => 1,
            self::Medium => 2,
            self::Low => 3,
        };
    }
}
