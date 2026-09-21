<?php

namespace App\Analysis\Contracts;

use App\Analysis\AuditContext;
use App\Analysis\SectionResult;

/**
 * Produces one section of the report from the downloaded page.
 *
 * Implementations must be idempotent: the queue may run them more than once
 * for the same audit (retries), and the result simply replaces the previous one.
 */
interface Analyzer
{
    public function analyze(AuditContext $context): SectionResult;
}
