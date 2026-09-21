<?php

namespace App\Security\Http;

use RuntimeException;

final class TooManyRedirectsException extends RuntimeException
{
    public function __construct(public readonly int $maxRedirects)
    {
        parent::__construct("La URL encadena más de {$maxRedirects} redirecciones.");
    }
}
