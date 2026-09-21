<?php

namespace App\Http\Requests;

use App\Enums\AuditStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAuditsRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 10;

    public const MAX_PER_PAGE = 50;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(AuditStatus::class)],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.enum' => 'El estado debe ser uno de: '.implode(', ', AuditStatus::values()).'.',
            'search.max' => 'La búsqueda no puede superar los :max caracteres.',
            'per_page.integer' => 'per_page debe ser un número entero.',
            'per_page.min' => 'per_page debe ser al menos :min.',
            'per_page.max' => 'per_page no puede ser mayor que :max.',
            'page.integer' => 'page debe ser un número entero.',
            'page.min' => 'page debe ser al menos :min.',
        ];
    }

    public function status(): ?AuditStatus
    {
        $status = $this->validated('status');

        return is_string($status) ? AuditStatus::from($status) : null;
    }

    public function search(): ?string
    {
        $search = trim((string) $this->validated('search'));

        return $search === '' ? null : $search;
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? self::DEFAULT_PER_PAGE);
    }
}
