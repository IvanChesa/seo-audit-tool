<?php

namespace App\Security\Http;

use RuntimeException;

final class ResponseTooLargeException extends RuntimeException
{
    public function __construct(public readonly int $maxBytes)
    {
        parent::__construct(sprintf(
            'La respuesta supera el tamaño máximo permitido (%s MB).',
            rtrim(rtrim(number_format($maxBytes / 1048576, 1, ',', ''), '0'), ','),
        ));
    }
}
