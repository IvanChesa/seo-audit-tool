<?php

namespace App\Analysis\Analyzers;

use App\Analysis\AuditContext;
use App\Analysis\Check;
use App\Analysis\CheckStatus;
use App\Analysis\Contracts\Analyzer;
use App\Analysis\HtmlDocument;
use App\Analysis\Issue;
use App\Analysis\SectionResult;
use DOMElement;
use Illuminate\Support\Str;

/**
 * H1–H6 outline: number and content of the H1, hierarchy jumps and empty headings.
 */
final class HeadingsAnalyzer implements Analyzer
{
    /** Headings stored in the report (the counts always include all of them). */
    public const MAX_STORED = 150;

    private const MAX_TEXT_LENGTH = 200;

    public function analyze(AuditContext $context): SectionResult
    {
        $headings = $this->extractHeadings($context->document());
        $counts = $this->countByLevel($headings);
        $h1Texts = array_values(array_map(
            fn (array $heading) => $heading['text'],
            array_filter($headings, fn (array $heading) => $heading['level'] === 1),
        ));
        $skips = $this->findSkippedLevels($headings);
        $emptyCount = count(array_filter($headings, fn (array $heading) => $heading['text'] === ''));

        $checks = [];
        $issues = [];

        if ($counts['h1'] === 0) {
            $checks[] = new Check('h1', 'Encabezado H1', CheckStatus::Fail, 'Ninguno');
            $issues[] = Issue::high(
                'missing_h1',
                'La página no tiene un encabezado H1',
                'Añade un único <h1> que resuma el tema principal de la página. Ayuda a buscadores y lectores de pantalla a entender de qué trata.',
                $headings === [] ? 'La página no contiene ningún encabezado (H1–H6).' : 'Hay '.count($headings).' encabezados, pero ninguno es H1.',
            );
        } elseif ($counts['h1'] > 1) {
            $checks[] = new Check('h1', 'Encabezado H1', CheckStatus::Warning, "{$counts['h1']} encabezados H1");
            $issues[] = Issue::low(
                'multiple_h1',
                'Hay más de un H1',
                'Google admite varios H1, pero reservar uno solo para el tema principal y usar H2–H6 para las secciones hace la estructura más clara.',
                'H1 encontrados: '.implode(' · ', array_map(fn (string $text) => '«'.Str::limit($text, 60).'»', array_slice($h1Texts, 0, 5))).'.',
            );
        } else {
            $checks[] = new Check('h1', 'Encabezado H1', $h1Texts[0] === '' ? CheckStatus::Warning : CheckStatus::Pass, $h1Texts[0] === '' ? 'Vacío' : '«'.Str::limit($h1Texts[0], 80).'»');
        }

        if ($skips === []) {
            $checks[] = new Check('hierarchy', 'Jerarquía', CheckStatus::Pass, 'Sin saltos de nivel');
        } else {
            $checks[] = new Check('hierarchy', 'Jerarquía', CheckStatus::Warning, count($skips).' saltos de nivel');
            $issues[] = Issue::low(
                'skipped_heading_levels',
                'La jerarquía de encabezados salta niveles',
                'Usa los niveles de forma consecutiva (H1 → H2 → H3). Saltarse niveles dificulta la navegación con lectores de pantalla y la comprensión de la estructura.',
                'Ejemplos: '.implode(' · ', array_slice($skips, 0, 3)).'.',
            );
        }

        if ($emptyCount > 0) {
            $checks[] = new Check('empty_headings', 'Encabezados vacíos', CheckStatus::Warning, (string) $emptyCount);
            $issues[] = Issue::low(
                'empty_headings',
                'Hay encabezados vacíos',
                'Rellena o elimina los encabezados sin texto: no aportan estructura y los lectores de pantalla los anuncian igualmente.',
                "{$emptyCount} encabezado(s) no contienen texto.",
            );
        } else {
            $checks[] = new Check('empty_headings', 'Encabezados vacíos', CheckStatus::Pass, 'Ninguno');
        }

        $checks[] = new Check('total', 'Total de encabezados', CheckStatus::Info, (string) count($headings));

        return SectionResult::completed($checks, $issues, [
            'counts' => $counts,
            'h1' => $h1Texts,
            'headings' => array_slice($headings, 0, self::MAX_STORED),
            'truncated' => count($headings) > self::MAX_STORED,
        ]);
    }

    /**
     * Headings in document order. An image with alt text inside a heading
     * counts as its text (e.g. a logo used as H1).
     *
     * @return list<array{level: int, text: string}>
     */
    private function extractHeadings(HtmlDocument $document): array
    {
        return array_map(function (DOMElement $element): array {
            $text = HtmlDocument::normalizeText($element->textContent);

            if ($text === '') {
                foreach ($element->getElementsByTagName('img') as $image) {
                    $text = HtmlDocument::normalizeText($image->getAttribute('alt'));

                    if ($text !== '') {
                        break;
                    }
                }
            }

            return [
                'level' => (int) substr(strtolower($element->nodeName), 1),
                'text' => Str::limit($text, self::MAX_TEXT_LENGTH),
            ];
        }, $document->elements('h1, h2, h3, h4, h5, h6'));
    }

    /**
     * @param  list<array{level: int, text: string}>  $headings
     * @return array{h1: int, h2: int, h3: int, h4: int, h5: int, h6: int}
     */
    private function countByLevel(array $headings): array
    {
        $levels = array_count_values(array_column($headings, 'level'));

        return [
            'h1' => $levels[1] ?? 0,
            'h2' => $levels[2] ?? 0,
            'h3' => $levels[3] ?? 0,
            'h4' => $levels[4] ?? 0,
            'h5' => $levels[5] ?? 0,
            'h6' => $levels[6] ?? 0,
        ];
    }

    /**
     * A skip is going deeper by more than one level (H2 → H4). Going back up
     * any number of levels (H4 → H2) is valid.
     *
     * @param  list<array{level: int, text: string}>  $headings
     * @return list<string>
     */
    private function findSkippedLevels(array $headings): array
    {
        $skips = [];
        $previous = null;

        foreach ($headings as $heading) {
            if ($previous !== null && $heading['level'] > $previous + 1) {
                $skips[] = "H{$previous} → H{$heading['level']} («".Str::limit($heading['text'], 40).'»)';
            }

            $previous = $heading['level'];
        }

        return $skips;
    }
}
