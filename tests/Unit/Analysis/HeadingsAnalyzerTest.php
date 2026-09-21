<?php

namespace Tests\Unit\Analysis;

use App\Analysis\Analyzers\HeadingsAnalyzer;
use App\Analysis\SectionResult;
use Tests\TestCase;

class HeadingsAnalyzerTest extends TestCase
{
    private function analyze(string $body): SectionResult
    {
        return (new HeadingsAnalyzer)->analyze($this->context("<html><body>{$body}</body></html>"));
    }

    /**
     * @return list<string>
     */
    private function issueCodes(SectionResult $result): array
    {
        return array_map(fn ($issue) => $issue->code, $result->issues);
    }

    public function test_a_correct_outline_has_no_issues(): void
    {
        $result = (new HeadingsAnalyzer)->analyze($this->context($this->fixture('good-page.html')));

        $this->assertSame([], $this->issueCodes($result));
        $this->assertSame(100, $result->score);
        $this->assertSame(['Cómo cuidar las plantas de interior'], $result->data['h1']);
        $this->assertSame(['h1' => 1, 'h2' => 3, 'h3' => 1, 'h4' => 0, 'h5' => 0, 'h6' => 0], $result->data['counts']);
    }

    public function test_headings_are_listed_in_document_order_with_normalised_text(): void
    {
        $result = $this->analyze("<h2>Segundo</h2><h1>  Título\n  principal </h1><h2>Otro</h2>");

        $this->assertSame([
            ['level' => 2, 'text' => 'Segundo'],
            ['level' => 1, 'text' => 'Título principal'],
            ['level' => 2, 'text' => 'Otro'],
        ], $result->data['headings']);
    }

    public function test_missing_h1(): void
    {
        $result = $this->analyze('<h2>Sección</h2>');

        $this->assertSame(['missing_h1'], $this->issueCodes($result));
        $this->assertSame('high', $result->issues[0]->severity->value);
        $this->assertSame(80, $result->score);
    }

    public function test_multiple_h1_is_reported_with_their_texts(): void
    {
        $result = $this->analyze('<h1>Primero</h1><h1>Segundo</h1>');

        $this->assertSame(['multiple_h1'], $this->issueCodes($result));
        $this->assertStringContainsString('«Primero»', (string) $result->issues[0]->evidence);
    }

    public function test_skipped_levels_are_detected_but_going_back_up_is_fine(): void
    {
        $skipped = $this->analyze('<h1>A</h1><h3>C</h3>');
        $upwards = $this->analyze('<h1>A</h1><h2>B</h2><h3>C</h3><h2>D</h2><h3>E</h3>');

        $this->assertSame(['skipped_heading_levels'], $this->issueCodes($skipped));
        $this->assertStringContainsString('H1 → H3', (string) $skipped->issues[0]->evidence);
        $this->assertSame([], $this->issueCodes($upwards));
    }

    public function test_empty_headings_and_image_alt_as_heading_text(): void
    {
        $result = $this->analyze('<h1><img src="logo.png" alt="Mi empresa"></h1><h2></h2>');

        $this->assertSame(['Mi empresa'], $result->data['h1']);
        $this->assertSame(['empty_headings'], $this->issueCodes($result));
    }

    public function test_a_page_without_headings(): void
    {
        $result = $this->analyze('<p>Solo texto</p>');

        $this->assertSame(['missing_h1'], $this->issueCodes($result));
        $this->assertSame([], $result->data['headings']);
        $this->assertFalse($result->data['truncated']);
    }
}
