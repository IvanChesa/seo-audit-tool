<?php

namespace Tests\Unit\Analysis;

use App\Analysis\Issue;
use App\Analysis\ScoreCalculator;
use App\Enums\Section;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScoreCalculatorTest extends TestCase
{
    public function test_section_weights_add_up_to_100(): void
    {
        $this->assertSame(100, array_sum(array_map(fn (Section $section) => $section->weight(), Section::cases())));
    }

    public function test_a_section_without_issues_scores_100(): void
    {
        $this->assertSame(100, ScoreCalculator::sectionScore([]));
    }

    public function test_each_issue_subtracts_the_penalty_of_its_severity(): void
    {
        $issues = [
            Issue::critical('a', 'A', 'fix'), // 40
            Issue::high('b', 'B', 'fix'),     // 20
            Issue::medium('c', 'C', 'fix'),   // 10
            Issue::low('d', 'D', 'fix'),      // 5
        ];

        $this->assertSame(25, ScoreCalculator::sectionScore($issues));
    }

    public function test_the_section_score_never_goes_below_zero(): void
    {
        $issues = array_fill(0, 5, Issue::critical('a', 'A', 'fix'));

        $this->assertSame(0, ScoreCalculator::sectionScore($issues));
    }

    public function test_global_score_is_the_weighted_average_of_all_sections(): void
    {
        $scores = [
            'technical' => 100, // 25 %
            'meta' => 80,       // 20 %
            'headings' => 60,   // 15 %
            'content' => 40,    // 15 %
            'links' => 100,     // 15 %
            'performance' => 50, // 10 %
        ];

        // (2500 + 1600 + 900 + 600 + 1500 + 500) / 100 = 76
        $this->assertSame(76, ScoreCalculator::globalScore($scores));
    }

    public function test_sections_without_score_are_excluded_and_their_weight_redistributed(): void
    {
        $scores = ['technical' => 100, 'meta' => 80, 'headings' => 60, 'content' => 40, 'links' => 100];

        // Without performance the remaining weights add up to 90:
        // (2500 + 1600 + 900 + 600 + 1500) / 90 = 78.9 → 79
        $this->assertSame(79, ScoreCalculator::globalScore($scores));

        $weights = ScoreCalculator::effectiveWeights($scores);
        $this->assertSame(0.0, $weights['performance']);
        $this->assertSame(27.8, $weights['technical']);
        $this->assertEqualsWithDelta(100, array_sum($weights), 0.2);
    }

    public function test_there_is_no_global_score_when_no_section_produced_one(): void
    {
        $this->assertNull(ScoreCalculator::globalScore([]));
    }

    public function test_unknown_section_keys_are_ignored(): void
    {
        $this->assertSame(90, ScoreCalculator::globalScore(['meta' => 90, 'legacy_type' => 0]));
    }

    public function test_critical_issues_cap_the_global_score(): void
    {
        $scores = ['technical' => 100, 'meta' => 60, 'headings' => 100, 'content' => 100, 'links' => 100];

        $this->assertSame(91, ScoreCalculator::globalScore($scores));
        $this->assertSame(ScoreCalculator::CRITICAL_CAP, ScoreCalculator::globalScore($scores, hasCriticalIssues: true));
        // The cap never raises a lower score.
        $this->assertSame(30, ScoreCalculator::globalScore(['meta' => 30], hasCriticalIssues: true));
    }

    #[DataProvider('ratings')]
    public function test_ratings_follow_lighthouse_bands(?int $score, ?string $rating): void
    {
        $this->assertSame($rating, ScoreCalculator::rating($score));
    }

    /**
     * @return array<string, array{int|null, string|null}>
     */
    public static function ratings(): array
    {
        return [
            'no score' => [null, null],
            '100' => [100, 'good'],
            '90' => [90, 'good'],
            '89' => [89, 'needs_improvement'],
            '50' => [50, 'needs_improvement'],
            '49' => [49, 'poor'],
            '0' => [0, 'poor'],
        ];
    }

    public function test_it_detects_critical_issues_in_stored_arrays(): void
    {
        $this->assertTrue(ScoreCalculator::containsCritical([['severity' => 'low'], ['severity' => 'critical']]));
        $this->assertFalse(ScoreCalculator::containsCritical([['severity' => 'high'], ['code' => 'x']]));
    }
}
