<?php

namespace App\Models\Exceptions;

use App\Enums\AuditStatus;
use LogicException;

final class InvalidStatusTransition extends LogicException
{
    public static function between(?AuditStatus $from, AuditStatus $to, int $auditId): self
    {
        return new self(sprintf(
            'Audit #%d cannot move from "%s" to "%s".',
            $auditId,
            $from->value ?? 'deleted',
            $to->value,
        ));
    }
}
