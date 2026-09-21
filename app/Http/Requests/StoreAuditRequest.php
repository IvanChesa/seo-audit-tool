<?php

namespace App\Http\Requests;

use App\Rules\PublicUrl;
use App\Security\SafeUrl;
use App\Security\UrlNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class StoreAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * "example.com" is accepted and treated as "https://example.com".
     */
    protected function prepareForValidation(): void
    {
        $url = $this->input('url');

        if (is_string($url)) {
            $this->merge(['url' => UrlNormalizer::withDefaultScheme($url)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => ['bail', 'required', 'string', 'max:'.UrlNormalizer::MAX_LENGTH, app(PublicUrl::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => 'Introduce la URL de la página que quieres auditar.',
            'url.string' => 'La URL no tiene un formato válido.',
            'url.max' => 'La URL no puede superar los :max caracteres.',
        ];
    }

    /**
     * The normalised URL that will be stored and audited.
     */
    public function safeUrl(): SafeUrl
    {
        return app(UrlNormalizer::class)->normalize((string) $this->validated('url'));
    }
}
