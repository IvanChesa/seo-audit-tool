<?php

namespace App\Analysis\Support;

enum LinkState: string
{
    /** Responded with 2xx/3xx. */
    case Ok = 'ok';

    /** 404, 410, other 4xx, 5xx, or no response at all. */
    case Broken = 'broken';

    /** 401, 403, 429...: the server refused an automated check, so it cannot be verified. */
    case Restricted = 'restricted';

    /** Not requested: the URL is not public (private IP, local host...) or not allowed. */
    case Skipped = 'skipped';
}
