<?php

namespace App\Analysis;

/**
 * One measured metric of a section (e.g. "title length: 72 characters").
 * The frontend renders checks as a table and explains each key.
 */
final readonly class Check
{
    public function __construct(
        public string $key,
        public string $label,
        public CheckStatus $status,
        public ?string $value = null,
    ) {}

    /**
     * @return array{key: string, label: string, status: string, value: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'status' => $this->status->value,
            'value' => $this->value,
        ];
    }
}
