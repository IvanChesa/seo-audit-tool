<?php

namespace App\Analysis;

enum CheckStatus: string
{
    case Pass = 'pass';
    case Warning = 'warning';
    case Fail = 'fail';
    /** Informative value, not evaluated. */
    case Info = 'info';
    /** The check could not be performed (e.g. robots.txt timed out). */
    case Unknown = 'unknown';
}
