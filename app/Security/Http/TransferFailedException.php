<?php

namespace App\Security\Http;

use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * Network-level failure (no usable HTTP response). The message is safe for
 * end users; the underlying cURL error is kept as the previous exception so
 * it can be logged, but it is never sent to the client.
 */
final class TransferFailedException extends RuntimeException
{
    private const TIMEOUT = 28;

    private const DNS_ERRORS = [6];

    private const TLS_ERRORS = [35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91];

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly bool $retryable,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromClientException(ConnectionException|RequestException $exception): self
    {
        $errno = self::curlErrorNumber($exception);

        return match (true) {
            $errno === self::TIMEOUT => new self('timeout', 'El servidor tardó demasiado en responder.', true, $exception),
            in_array($errno, self::DNS_ERRORS, true) => new self('dns_error', 'No se ha podido resolver el dominio.', true, $exception),
            in_array($errno, self::TLS_ERRORS, true) => new self('tls_error', 'No se pudo establecer una conexión segura con el servidor (error TLS o certificado no válido).', false, $exception),
            default => new self('connection_failed', 'No se pudo conectar con el servidor.', true, $exception),
        };
    }

    private static function curlErrorNumber(\Throwable $exception): ?int
    {
        $previous = $exception->getPrevious();

        if ($previous instanceof GuzzleConnectException || $previous instanceof GuzzleRequestException) {
            $errno = $previous->getHandlerContext()['errno'] ?? null;

            if (is_int($errno)) {
                return $errno;
            }
        }

        // Fallback for exceptions built without a handler context.
        return preg_match('/cURL error (\d+)/', $exception->getMessage(), $match) === 1 ? (int) $match[1] : null;
    }
}
