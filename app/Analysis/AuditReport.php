<?php

namespace App\Analysis;

use App\Enums\AuditStatus;
use App\Enums\Section;
use App\Enums\SectionStatus;
use App\Enums\Severity;
use App\Models\Audit;
use App\Models\AuditResult;
use App\Models\BrokenLink;
use Illuminate\Support\Collection;

/**
 * Builds the read model of an audit for the API: progress, sections,
 * prioritised issues and score breakdown. Expects results and brokenLinks
 * to be loaded.
 */
final class AuditReport
{
    /** @var Collection<string, AuditResult> */
    private readonly Collection $results;

    public function __construct(private readonly Audit $audit)
    {
        $this->results = $audit->results->keyBy('type');
    }

    /**
     * @return array{completed_steps: int, total_steps: int, percentage: int, steps: list<array{key: string, label: string, status: string}>}
     */
    public function progress(): array
    {
        $steps = [['key' => 'fetch', 'label' => 'Descarga de la página', 'status' => $this->fetchStatus()]];

        foreach (Section::cases() as $section) {
            $steps[] = ['key' => $section->value, 'label' => $section->label(), 'status' => $this->sectionStatus($section)];
        }

        $done = count(array_filter($steps, fn (array $step) => in_array($step['status'], ['completed', 'skipped', 'failed'], true)));

        return [
            'completed_steps' => $done,
            'total_steps' => count($steps),
            'percentage' => (int) round($done / count($steps) * 100),
            'steps' => $steps,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sections(): array
    {
        return array_map(function (Section $section): array {
            $result = $this->results->get($section->value);
            $data = $result->data ?? [];

            if ($section === Section::Links && $result !== null) {
                $data['broken_links'] = $this->audit->brokenLinks
                    ->map(fn (BrokenLink $link) => [
                        'url' => $link->url,
                        'is_internal' => $link->is_internal,
                        'status_code' => $link->status_code,
                        'error' => $link->error,
                        'link_text' => $link->link_text,
                    ])
                    ->values()
                    ->all();
            }

            return [
                'key' => $section->value,
                'label' => $section->label(),
                'weight' => $section->weight(),
                'status' => $this->sectionStatus($section),
                'score' => $result?->score,
                'score_rating' => ScoreCalculator::rating($result?->score),
                'data' => (object) $data,
                'issues' => $result->issues ?? [],
                'error' => $result?->error_code === null ? null : [
                    'code' => $result->error_code,
                    'message' => $result->error_message,
                ],
            ];
        }, Section::cases());
    }

    /**
     * Every issue of the report, most severe first.
     *
     * @return list<array<string, mixed>>
     */
    public function issues(): array
    {
        $issues = [];

        foreach (Section::cases() as $order => $section) {
            foreach ($this->results->get($section->value)->issues ?? [] as $issue) {
                $issues[] = ['section' => $section->value, 'section_label' => $section->label(), ...$issue, '_order' => $order];
            }
        }

        usort($issues, fn (array $a, array $b) => [self::rank($a['severity'] ?? null), $a['_order']] <=> [self::rank($b['severity'] ?? null), $b['_order']]);

        return array_map(function (array $issue): array {
            unset($issue['_order']);

            return $issue;
        }, $issues);
    }

    /**
     * @return array{critical: int, high: int, medium: int, low: int, total: int}
     */
    public function issuesSummary(): array
    {
        $summary = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];

        foreach ($this->issues() as $issue) {
            if (isset($summary[$issue['severity'] ?? ''])) {
                $summary[$issue['severity']]++;
            }
        }

        return [...$summary, 'total' => array_sum($summary)];
    }

    /**
     * @return array<string, mixed>
     */
    public function scoreBreakdown(): array
    {
        $scores = [];
        $hasCritical = false;

        foreach ($this->results as $type => $result) {
            if ($result->status === SectionStatus::Completed && $result->score !== null && Section::tryFrom((string) $type) !== null) {
                $scores[(string) $type] = (int) $result->score;
                $hasCritical = $hasCritical || ScoreCalculator::containsCritical($result->issues ?? []);
            }
        }

        $effectiveWeights = ScoreCalculator::effectiveWeights($scores);
        $weightedAverage = ScoreCalculator::globalScore($scores);

        return [
            'weighted_average' => $weightedAverage,
            'critical_cap' => ScoreCalculator::CRITICAL_CAP,
            'critical_cap_applied' => $hasCritical && $weightedAverage !== null && $weightedAverage > ScoreCalculator::CRITICAL_CAP,
            'sections' => array_map(fn (Section $section) => [
                'key' => $section->value,
                'label' => $section->label(),
                'weight' => $section->weight(),
                'effective_weight' => $effectiveWeights[$section->value],
                'score' => $scores[$section->value] ?? null,
                'counted' => array_key_exists($section->value, $scores),
            ], Section::cases()),
        ];
    }

    private function fetchStatus(): string
    {
        return match (true) {
            $this->audit->http_status !== null || $this->results->isNotEmpty() => 'completed',
            $this->audit->status === AuditStatus::Pending => 'pending',
            $this->audit->status === AuditStatus::Processing => 'running',
            $this->audit->status === AuditStatus::Failed => 'failed',
            default => 'completed',
        };
    }

    private function sectionStatus(Section $section): string
    {
        $result = $this->results->get($section->value);

        if ($result !== null) {
            return $result->status->value;
        }

        if ($this->audit->status->isFinished()) {
            return 'not_run';
        }

        return $this->fetchStatus() === 'completed' ? 'running' : 'pending';
    }

    private static function rank(mixed $severity): int
    {
        return is_string($severity) ? (Severity::tryFrom($severity)?->rank() ?? 99) : 99;
    }
}
