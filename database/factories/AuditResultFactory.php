<?php

namespace Database\Factories;

use App\Enums\Section;
use App\Enums\SectionStatus;
use App\Models\Audit;
use App\Models\AuditResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditResult>
 */
class AuditResultFactory extends Factory
{
    protected $model = AuditResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audit_id' => Audit::factory(),
            'type' => Section::Meta->value,
            'status' => SectionStatus::Completed,
            'score' => 100,
            'data' => ['checks' => []],
            'issues' => [],
        ];
    }

    public function section(Section $section, ?int $score = 100): static
    {
        return $this->state(fn () => ['type' => $section->value, 'score' => $score]);
    }

    /**
     * @param  list<array<string, string|null>>  $issues
     */
    public function withIssues(array $issues): static
    {
        return $this->state(fn () => ['issues' => $issues]);
    }

    public function failed(string $code = 'analysis_failed'): static
    {
        return $this->state(fn () => [
            'status' => SectionStatus::Failed,
            'score' => null,
            'data' => [],
            'error_code' => $code,
            'error_message' => 'No se pudo completar este análisis.',
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn () => [
            'status' => SectionStatus::Skipped,
            'score' => null,
            'data' => [],
            'error_code' => 'pagespeed_not_configured',
            'error_message' => 'La medición de rendimiento no se ha ejecutado.',
        ]);
    }
}
