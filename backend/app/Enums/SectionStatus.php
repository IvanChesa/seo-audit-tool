<?php

namespace App\Enums;

enum SectionStatus: string
{
    /** The analysis ran and produced a score. */
    case Completed = 'completed';

    /** The analysis was intentionally not run (e.g. PageSpeed without an API key). */
    case Skipped = 'skipped';

    /** The analysis could not be completed; its weight is excluded from the score. */
    case Failed = 'failed';
}
