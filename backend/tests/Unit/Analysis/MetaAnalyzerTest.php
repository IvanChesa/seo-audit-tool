<?php

namespace Tests\Unit\Analysis;

use App\Analysis\Analyzers\MetaAnalyzer;
use App\Analysis\SectionResult;
use App\Enums\SectionStatus;
use Tests\TestCase;

class MetaAnalyzerTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function analyze(string $html, array $overrides = []): SectionResult
    {
        return (new MetaAnalyzer)->analyze($this->context($html, $overrides));
    }

    /**
     * @return list<string>
     */
    private function issueCodes(SectionResult $result): array
    {
        return array_map(fn ($issue) => $issue->code, $result->issues);
    }

    private function pageWithHead(string $content): string
    {
        return "<!doctype html><html><head>{$content}</head><body></body></html>";
    }

    public function test_a_well_optimised_page_has_no_issues(): void
    {
        $result = $this->analyze($this->fixture('good-page.html'));

        $this->assertSame(SectionStatus::Completed, $result->status);
        $this->assertSame([], $this->issueCodes($result));
        $this->assertSame(100, $result->score);
        $this->assertSame('Guía para cuidar plantas de interior en casa', $result->data['title']);
        $this->assertSame(44, $result->data['title_length']);
        $this->assertSame('https://example.com/', $result->data['canonical']);
        $this->assertSame('https://example.com/portada.jpg', $result->data['open_graph']['og:image']);
    }

    public function test_a_poor_page_reports_every_problem_with_its_severity(): void
    {
        $result = $this->analyze($this->fixture('bad-page.html'));
        $severities = array_column($result->issuesToArray(), 'severity', 'code');

        $this->assertSame([
            'noindex' => 'critical',
            'missing_title' => 'high',
            'missing_meta_description' => 'medium',
            'missing_canonical' => 'medium',
            'incomplete_open_graph' => 'low',
        ], $severities);
        // 100 − 40 − 20 − 10 − 10 − 5
        $this->assertSame(15, $result->score);
    }

    public function test_issues_are_sorted_from_most_to_least_severe(): void
    {
        $result = $this->analyze($this->fixture('bad-page.html'));

        $this->assertSame('noindex', $result->issues[0]->code);
        $this->assertSame('incomplete_open_graph', $result->issues[array_key_last($result->issues)]->code);
    }

    public function test_title_length_is_measured_in_characters_not_bytes(): void
    {
        // 30 characters but 36 bytes in UTF-8: must not be "too long" nor "too short".
        $title = 'Canción ñandú pingüino acción';
        $this->assertSame(29, mb_strlen($title));

        $result = $this->analyze($this->pageWithHead("<title>{$title}é</title>"));

        $this->assertNotContains('title_too_short', $this->issueCodes($result));
        $this->assertSame(30, $result->data['title_length']);
    }

    public function test_long_and_short_titles_and_descriptions(): void
    {
        $long = $this->analyze($this->pageWithHead('<title>'.str_repeat('Palabra ', 10).'</title><meta name="description" content="'.str_repeat('texto ', 40).'">'));
        $short = $this->analyze($this->pageWithHead('<title>Inicio</title><meta name="description" content="Breve.">'));

        $this->assertContains('title_too_long', $this->issueCodes($long));
        $this->assertContains('meta_description_too_long', $this->issueCodes($long));
        $this->assertContains('title_too_short', $this->issueCodes($short));
        $this->assertContains('meta_description_too_short', $this->issueCodes($short));
    }

    public function test_svg_titles_are_not_counted_as_page_titles(): void
    {
        $html = '<html><head><title>Título real de la página de ejemplo</title></head><body><svg><title>Icono</title></svg></body></html>';

        $result = $this->analyze($html);

        $this->assertNotContains('multiple_titles', $this->issueCodes($result));
        $this->assertSame('Título real de la página de ejemplo', $result->data['title']);
    }

    public function test_meta_names_are_case_insensitive(): void
    {
        $result = $this->analyze($this->pageWithHead('<META NAME="Description" CONTENT="Una descripción suficientemente larga para superar el mínimo de setenta caracteres.">'));

        $this->assertNotContains('missing_meta_description', $this->issueCodes($result));
    }

    public function test_canonical_problems(): void
    {
        $multiple = $this->analyze($this->pageWithHead('<link rel="canonical" href="/a"><link rel="canonical" href="/b">'));
        $elsewhere = $this->analyze($this->pageWithHead('<link rel="canonical" href="https://example.com/otra">'));
        $invalid = $this->analyze($this->pageWithHead('<link rel="canonical" href="javascript:void(0)">'));
        $relativeSelf = $this->analyze($this->pageWithHead('<link rel="Canonical" href="/">'));

        $this->assertContains('multiple_canonicals', $this->issueCodes($multiple));
        $this->assertContains('canonical_points_elsewhere', $this->issueCodes($elsewhere));
        $this->assertContains('invalid_canonical', $this->issueCodes($invalid));
        $this->assertSame('https://example.com/', $relativeSelf->data['canonical']);
        $this->assertNotContains('canonical_points_elsewhere', $this->issueCodes($relativeSelf));
    }

    public function test_noindex_in_the_x_robots_tag_header_is_detected(): void
    {
        $result = $this->analyze($this->fixture('good-page.html'), ['xRobotsTag' => 'noindex']);

        $this->assertSame(['noindex'], $this->issueCodes($result));
        $this->assertStringContainsString('X-Robots-Tag', (string) $result->issues[0]->evidence);
    }

    public function test_nofollow_without_noindex_is_a_medium_issue(): void
    {
        $result = $this->analyze($this->pageWithHead('<meta name="robots" content="index, nofollow">'));

        $this->assertContains('nofollow_page', $this->issueCodes($result));
        $this->assertNotContains('noindex', $this->issueCodes($result));
    }

    public function test_it_copes_with_an_empty_document(): void
    {
        $result = $this->analyze('');

        $this->assertSame(SectionStatus::Completed, $result->status);
        $this->assertContains('missing_title', $this->issueCodes($result));
        $this->assertNull($result->data['title']);
    }
}
