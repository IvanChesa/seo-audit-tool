<?php

namespace App\Security;

/**
 * Reasons why a URL cannot be requested. The message is safe to show to
 * end users: it never includes internal details such as resolved IPs.
 */
enum UrlRejection: string
{
    case Empty = 'empty';
    case TooLong = 'too_long';
    case InvalidFormat = 'invalid_format';
    case UnsupportedScheme = 'unsupported_scheme';
    case EmbeddedCredentials = 'embedded_credentials';
    case DisallowedPort = 'disallowed_port';
    case InvalidHost = 'invalid_host';
    case LocalHostname = 'local_hostname';
    case PrivateAddress = 'private_address';
    case UnresolvableHost = 'unresolvable_host';

    public function message(): string
    {
        return match ($this) {
            self::Empty => 'Introduce una URL.',
            self::TooLong => 'La URL no puede superar los '.UrlNormalizer::MAX_LENGTH.' caracteres.',
            self::InvalidFormat => 'La URL no tiene un formato válido.',
            self::UnsupportedScheme => 'Solo se admiten direcciones que empiecen por http:// o https://.',
            self::EmbeddedCredentials => 'La URL no puede incluir usuario ni contraseña.',
            self::DisallowedPort => 'El puerto de la URL no está permitido. Usa los puertos web habituales.',
            self::InvalidHost => 'El dominio de la URL no es válido.',
            self::LocalHostname => 'No se pueden auditar direcciones locales o de redes internas.',
            self::PrivateAddress => 'La URL apunta a una dirección IP privada, reservada o interna y no se puede auditar.',
            self::UnresolvableHost => 'No se ha podido resolver el dominio. Comprueba que existe y es público.',
        };
    }
}
