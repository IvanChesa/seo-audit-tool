<?php

namespace App\Analysis;

use App\Enums\Severity;

/**
 * A problem found in the page, with the evidence that proves it and a
 * concrete recommendation to fix it.
 */
final readonly class Issue
{
    public function __construct(
        public string $code,
        public Severity $severity,
        public string $title,
        public string $recommendation,
        public ?string $evidence = null,
    ) {}

    public static function critical(string $code, string $title, string $recommendation, ?string $evidence = null): self
    {
        return new self($code, Severity::Critical, $title, $recommendation, $evidence);
    }

    public static function high(string $code, string $title, string $recommendation, ?string $evidence = null): self
    {
        return new self($code, Severity::High, $title, $recommendation, $evidence);
    }

    public static function medium(string $code, string $title, string $recommendation, ?string $evidence = null): self
    {
        return new self($code, Severity::Medium, $title, $recommendation, $evidence);
    }

    public static function low(string $code, string $title, string $recommendation, ?string $evidence = null): self
    {
        return new self($code, Severity::Low, $title, $recommendation, $evidence);
    }

    /**
     * @return array{code: string, severity: string, title: string, evidence: string|null, recommendation: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity->value,
            'title' => $this->title,
            'evidence' => $this->evidence,
            'recommendation' => $this->recommendation,
        ];
    }
}
