<?php

namespace App\Analysis;

use App\Enums\Section;
use App\Enums\Severity;

/**
 * Scoring rules (also documented in the README):
 *
 *  1. Section score = 100 − Σ penalty(issue severity), never below 0.
 *     Penalties: critical 40, high 20, medium 10, low 5.
 *     Performance is the exception: it uses Lighthouse's own score.
 *  2. Global score = weighted average of the sections that produced a score.
 *     Skipped or failed sections are excluded and their weight is shared
 *     proportionally among the rest.
 *  3. If any critical issue exists (the page cannot be indexed), the global
 *     score is capped at 49: nothing else matters if search engines cannot
 *     show the page.
 */
final class ScoreCalculator
{
    public const CRITICAL_CAP = 49;

    public const GOOD_THRESHOLD = 90;

    public const POOR_THRESHOLD = 50;

    /**
     * @param  list<Issue>  $issues
     */
    public static function sectionScore(array $issues): int
    {
        $penalty = array_sum(array_map(fn (Issue $issue): int => $issue->severity->penalty(), $issues));

        return max(0, 100 - $penalty);
    }

    /**
     * @param  array<string, int>  $sectionScores  Section value => score, only for sections that produced one.
     */
    public static function globalScore(array $sectionScores, bool $hasCriticalIssues = false): ?int
    {
        $weightedSum = 0;
        $totalWeight = 0;

        foreach (Section::cases() as $section) {
            if (! array_key_exists($section->value, $sectionScores)) {
                continue;
            }

            $weightedSum += $sectionScores[$section->value] * $section->weight();
            $totalWeight += $section->weight();
        }

        if ($totalWeight === 0) {
            return null;
        }

        $score = (int) round($weightedSum / $totalWeight);

        return $hasCriticalIssues ? min($score, self::CRITICAL_CAP) : $score;
    }

    /**
     * Weight each section actually had in the global score (percentages that
     * add up to 100 among the sections with a score).
     *
     * @param  array<string, int>  $sectionScores
     * @return array<string, float>
     */
    public static function effectiveWeights(array $sectionScores): array
    {
        $scored = array_filter(Section::cases(), fn (Section $section) => array_key_exists($section->value, $sectionScores));
        $total = array_sum(array_map(fn (Section $section) => $section->weight(), $scored));
        $weights = [];

        foreach (Section::cases() as $section) {
            $weights[$section->value] = $total > 0 && array_key_exists($section->value, $sectionScores)
                ? round($section->weight() / $total * 100, 1)
                : 0.0;
        }

        return $weights;
    }

    /**
     * Same bands as Lighthouse: good ≥ 90, needs improvement 50–89, poor < 50.
     */
    public static function rating(?int $score): ?string
    {
        return match (true) {
            $score === null => null,
            $score >= self::GOOD_THRESHOLD => 'good',
            $score >= self::POOR_THRESHOLD => 'needs_improvement',
            default => 'poor',
        };
    }

    /**
     * @param  iterable<array{severity?: mixed}>  $issues
     */
    public static function containsCritical(iterable $issues): bool
    {
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? null) === Severity::Critical->value) {
                return true;
            }
        }

        return false;
    }
}
