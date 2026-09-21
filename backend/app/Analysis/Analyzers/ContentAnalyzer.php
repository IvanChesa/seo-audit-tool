<?php

namespace App\Analysis\Analyzers;

use App\Analysis\AuditContext;
use App\Analysis\Check;
use App\Analysis\CheckStatus;
use App\Analysis\Contracts\Analyzer;
use App\Analysis\HtmlDocument;
use App\Analysis\Issue;
use App\Analysis\SectionResult;
use App\Analysis\Support\KeywordExtractor;
use App\Enums\Severity;
use DOMElement;
use Illuminate\Support\Str;

/**
 * Amount of visible text, most frequent terms and images without alt text.
 *
 * Keyword density is reported as information only: it is not a ranking
 * factor by itself, so the only issue raised is an obvious over-repetition
 * in texts long enough for the percentage to be meaningful.
 */
final class ContentAnalyzer implements Analyzer
{
    public const THIN_CONTENT_WORDS = 300;

    public const VERY_THIN_CONTENT_WORDS = 100;

    /** Density above which a single term is flagged as possibly over-used. */
    public const STUFFING_DENSITY = 5.0;

    /** Below this length a density percentage says little. */
    public const STUFFING_MIN_WORDS = 200;

    private const MAX_IMAGE_EXAMPLES = 10;

    /** Elements whose text is not part of the main content. */
    private const NON_CONTENT_SELECTOR = 'script, style, noscript, template, svg, nav, footer, iframe';

    public function __construct(private readonly KeywordExtractor $keywords) {}

    public function analyze(AuditContext $context): SectionResult
    {
        // A fresh document: removing nodes must not affect other analyzers.
        $document = new HtmlDocument($context->page->html);

        $images = $this->analyzeImages($document);
        $words = $this->keywords->words($this->visibleText($document));
        $wordCount = count($words);
        $topTerms = $this->keywords->topTerms($words);

        $checks = [];
        $issues = [];

        if ($wordCount < self::THIN_CONTENT_WORDS) {
            $checks[] = new Check('word_count', 'Palabras en el contenido', CheckStatus::Warning, (string) $wordCount);
            $veryThin = $wordCount < self::VERY_THIN_CONTENT_WORDS;
            $issues[] = new Issue(
                code: 'low_word_count',
                severity: $veryThin ? Severity::Medium : Severity::Low,
                title: $veryThin ? 'Hay muy poco texto en la página' : 'El contenido es breve',
                recommendation: 'Si la página debe posicionar por búsquedas concretas, desarrolla el tema con contenido útil y original. En páginas de contacto, formularios o portadas muy visuales es normal tener poco texto.',
                evidence: "Se han contado {$wordCount} palabras en el contenido principal (sin menú, pie de página ni scripts).",
            );
        } else {
            $checks[] = new Check('word_count', 'Palabras en el contenido', CheckStatus::Pass, (string) $wordCount);
        }

        $top = $topTerms[0] ?? null;

        if ($top !== null) {
            $density = number_format($top['density'], 1, ',', '.');
            $checks[] = new Check('top_term', 'Término más frecuente', CheckStatus::Info, "«{$top['term']}» · {$top['count']} veces ({$density} %)");

            if ($wordCount >= self::STUFFING_MIN_WORDS && $top['density'] > self::STUFFING_DENSITY) {
                $issues[] = Issue::low(
                    'possible_keyword_stuffing',
                    'Un término se repite con mucha frecuencia',
                    'Revisa que el texto suene natural y usa sinónimos o variaciones. La densidad de palabras clave no es un factor de posicionamiento por sí misma, pero repetir en exceso empeora la lectura y puede considerarse spam.',
                    "«{$top['term']}» aparece {$top['count']} veces ({$density} % de las palabras).",
                );
            }
        }

        if ($images['total'] === 0) {
            $checks[] = new Check('images_alt', 'Imágenes con texto alternativo', CheckStatus::Info, 'La página no tiene imágenes');
        } elseif ($images['missing_alt'] > 0) {
            $checks[] = new Check('images_alt', 'Imágenes con texto alternativo', CheckStatus::Fail, "{$images['missing_alt']} de {$images['total']} sin alt");
            $examples = implode(', ', array_map(fn (string $src) => Str::limit($src, 80), array_slice($images['missing_alt_examples'], 0, 3)));
            $issues[] = Issue::medium(
                'images_missing_alt',
                'Hay imágenes sin atributo alt',
                'Añade un texto alternativo que describa cada imagen informativa (alt="…") y usa alt="" en las puramente decorativas. Mejora la accesibilidad y ayuda a los buscadores a entender las imágenes.',
                "{$images['missing_alt']} de {$images['total']} imágenes no tienen atributo alt".($examples !== '' ? " (p. ej. {$examples})." : '.'),
            );
        } else {
            $checks[] = new Check('images_alt', 'Imágenes con texto alternativo', CheckStatus::Pass, "{$images['total']} de {$images['total']}");
        }

        return SectionResult::completed($checks, $issues, [
            'word_count' => $wordCount,
            'top_terms' => $topTerms,
            'images' => $images,
        ]);
    }

    private function visibleText(HtmlDocument $document): string
    {
        $body = $document->elements('body')[0] ?? null;

        if ($body === null) {
            return '';
        }

        foreach ($document->elements(self::NON_CONTENT_SELECTOR) as $element) {
            $element->parentNode?->removeChild($element);
        }

        return HtmlDocument::normalizeText($body->textContent);
    }

    /**
     * Images hidden from assistive technology (role="presentation"/"none" or
     * aria-hidden="true") do not need alt text. alt="" marks a decorative image.
     *
     * @return array{total: int, missing_alt: int, decorative: int, missing_alt_examples: list<string>}
     */
    private function analyzeImages(HtmlDocument $document): array
    {
        $images = $document->elements('img');
        $missing = [];
        $decorative = 0;

        foreach ($images as $image) {
            if ($this->isHiddenFromAssistiveTech($image)) {
                continue;
            }

            if (! $image->hasAttribute('alt')) {
                $missing[] = trim($image->getAttribute('src')) ?: trim($image->getAttribute('data-src')) ?: '(sin src)';
            } elseif (trim($image->getAttribute('alt')) === '') {
                $decorative++;
            }
        }

        return [
            'total' => count($images),
            'missing_alt' => count($missing),
            'decorative' => $decorative,
            'missing_alt_examples' => array_slice($missing, 0, self::MAX_IMAGE_EXAMPLES),
        ];
    }

    private function isHiddenFromAssistiveTech(DOMElement $image): bool
    {
        $role = strtolower(trim($image->getAttribute('role')));

        return in_array($role, ['presentation', 'none'], true)
            || strtolower(trim($image->getAttribute('aria-hidden'))) === 'true';
    }
}
