<?php

namespace App\Analysis\Analyzers;

use App\Analysis\AuditContext;
use App\Analysis\Check;
use App\Analysis\CheckStatus;
use App\Analysis\Contracts\Analyzer;
use App\Analysis\Issue;
use App\Analysis\SectionResult;
use App\Analysis\Support\PageSpeedClient;
use App\Analysis\Support\PageSpeedException;

/**
 * Lab performance from PageSpeed Insights. Only runs when PAGESPEED_API_KEY
 * is configured; otherwise the section is reported as "not run" and its
 * weight is excluded from the global score.
 *
 * The section score is Lighthouse's performance score as is.
 */
final class PerformanceAnalyzer implements Analyzer
{
    /**
     * "Good" and "poor" thresholds published by Google (web.dev) for lab metrics.
     *
     * @var array<string, array{label: string, good: int|float, poor: int|float}>
     */
    public const THRESHOLDS = [
        'lcp' => ['label' => 'Largest Contentful Paint (LCP)', 'good' => 2500, 'poor' => 4000],
        'cls' => ['label' => 'Cumulative Layout Shift (CLS)', 'good' => 0.1, 'poor' => 0.25],
        'fcp' => ['label' => 'First Contentful Paint (FCP)', 'good' => 1800, 'poor' => 3000],
        'tbt' => ['label' => 'Total Blocking Time (TBT)', 'good' => 200, 'poor' => 600],
        'speed_index' => ['label' => 'Speed Index', 'good' => 3400, 'poor' => 5800],
    ];

    public function __construct(private readonly PageSpeedClient $pageSpeed) {}

    /**
     * @throws PageSpeedException when the failure is transient, so the job is retried
     */
    public function analyze(AuditContext $context): SectionResult
    {
        if (! $this->pageSpeed->isConfigured()) {
            return SectionResult::skipped(
                'pagespeed_not_configured',
                'La medición de rendimiento no se ha ejecutado porque no hay una clave de PageSpeed Insights configurada (PAGESPEED_API_KEY).',
            );
        }

        try {
            $report = $this->pageSpeed->run($context->page->finalUrl);
        } catch (PageSpeedException $e) {
            if ($e->retryable) {
                throw $e;
            }

            return SectionResult::failed($e->errorCode, $e->getMessage());
        }

        $score = $report['performance_score'];

        if ($score === null) {
            return SectionResult::failed('pagespeed_no_score', 'PageSpeed Insights no devolvió una puntuación de rendimiento para esta página.');
        }

        $strategy = $this->pageSpeed->strategy() === 'desktop' ? 'escritorio' : 'móvil';
        $checks = [new Check('performance_score', "Puntuación Lighthouse ({$strategy})", $this->statusForScore($score), "{$score}/100")];
        $issues = [];
        $metrics = [];

        foreach (self::THRESHOLDS as $key => $threshold) {
            $metric = $report['metrics'][$key] ?? ['value' => null, 'display' => null];
            $rating = $this->rating($metric['value'], $threshold);
            $metrics[$key] = [...$metric, 'rating' => $rating];
            $checks[] = new Check($key, $threshold['label'], match ($rating) {
                'good' => CheckStatus::Pass,
                'needs_improvement' => CheckStatus::Warning,
                'poor' => CheckStatus::Fail,
                default => CheckStatus::Unknown,
            }, $metric['display']);

            if ($rating === 'poor') {
                $issues[] = Issue::medium(
                    "poor_{$key}",
                    "{$threshold['label']} es deficiente",
                    'Consulta las oportunidades de mejora del informe de PageSpeed Insights para esta métrica.',
                    "Valor medido: {$metric['display']}.",
                );
            }
        }

        if ($score < 50) {
            $issues[] = Issue::high(
                'poor_performance',
                "El rendimiento en {$strategy} es deficiente",
                'Optimiza y comprime las imágenes, reduce el JavaScript y el CSS que bloquean el renderizado y aprovecha la caché del navegador. PageSpeed Insights detalla qué recursos mejorar.',
                "Puntuación de Lighthouse: {$score}/100.",
            );
        } elseif ($score < 90) {
            $issues[] = Issue::medium(
                'performance_needs_improvement',
                "El rendimiento en {$strategy} es mejorable",
                'Revisa las oportunidades de mejora de PageSpeed Insights; las que más ahorran suelen ser imágenes, JavaScript sin usar y fuentes.',
                "Puntuación de Lighthouse: {$score}/100.",
            );
        }

        return SectionResult::completed($checks, $issues, [
            'strategy' => $this->pageSpeed->strategy(),
            'performance_score' => $score,
            'metrics' => $metrics,
            'source' => 'PageSpeed Insights (Lighthouse, datos de laboratorio)',
        ], score: $score);
    }

    private function statusForScore(int $score): CheckStatus
    {
        return match (true) {
            $score >= 90 => CheckStatus::Pass,
            $score >= 50 => CheckStatus::Warning,
            default => CheckStatus::Fail,
        };
    }

    /**
     * @param  array{label: string, good: int|float, poor: int|float}  $threshold
     */
    private function rating(?float $value, array $threshold): ?string
    {
        return match (true) {
            $value === null => null,
            $value <= $threshold['good'] => 'good',
            $value <= $threshold['poor'] => 'needs_improvement',
            default => 'poor',
        };
    }
}
