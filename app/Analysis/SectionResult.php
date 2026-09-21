<?php

namespace App\Analysis;

use App\Enums\SectionStatus;

/**
 * What an analyzer returns. Named constructors keep the three possible
 * outcomes explicit: completed (with checks, issues and a score), skipped
 * or failed (with a reason that is safe to show to users).
 */
final readonly class SectionResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<Issue>  $issues
     * @param  list<array{url: string, is_internal: bool, status_code: int|null, error: string|null, link_text: string|null}>  $brokenLinks
     */
    private function __construct(
        public SectionStatus $status,
        public ?int $score,
        public array $data,
        public array $issues,
        public array $brokenLinks = [],
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}

    /**
     * @param  list<Check>  $checks
     * @param  list<Issue>  $issues
     * @param  array<string, mixed>  $data  Section-specific details.
     * @param  int|null  $score  Defaults to the penalty-based section score.
     * @param  list<array{url: string, is_internal: bool, status_code: int|null, error: string|null, link_text: string|null}>  $brokenLinks
     */
    public static function completed(
        array $checks,
        array $issues,
        array $data = [],
        ?int $score = null,
        array $brokenLinks = [],
    ): self {
        usort($issues, fn (Issue $a, Issue $b): int => $a->severity->rank() <=> $b->severity->rank());

        return new self(
            status: SectionStatus::Completed,
            score: $score ?? ScoreCalculator::sectionScore($issues),
            data: ['checks' => array_map(fn (Check $check): array => $check->toArray(), $checks)] + $data,
            issues: $issues,
            brokenLinks: $brokenLinks,
        );
    }

    public static function skipped(string $code, string $message): self
    {
        return new self(SectionStatus::Skipped, null, [], [], errorCode: $code, errorMessage: $message);
    }

    public static function failed(string $code, string $message): self
    {
        return new self(SectionStatus::Failed, null, [], [], errorCode: $code, errorMessage: $message);
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function issuesToArray(): array
    {
        return array_map(fn (Issue $issue): array => $issue->toArray(), $this->issues);
    }
}
