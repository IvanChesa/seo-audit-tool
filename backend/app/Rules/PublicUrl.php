<?php

namespace App\Rules;

use App\Security\UnsafeUrlException;
use App\Security\UrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The URL must be http(s), well formed and resolve only to public addresses.
 * The same check runs again right before every request (see UrlGuard), so
 * a DNS change between validation and download is not a bypass.
 */
final class PublicUrl implements ValidationRule
{
    public function __construct(private readonly UrlGuard $guard) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('La URL no tiene un formato válido.');

            return;
        }

        try {
            $this->guard->inspect($value);
        } catch (UnsafeUrlException $e) {
            $fail($e->getMessage());
        }
    }
}
