<?php

namespace App\Analysis\Analyzers;

use App\Analysis\AuditContext;
use App\Analysis\Check;
use App\Analysis\CheckStatus;
use App\Analysis\Contracts\Analyzer;
use App\Analysis\Issue;
use App\Analysis\SectionResult;
use App\Analysis\Support\ExtractedLink;
use App\Analysis\Support\LinkChecker;
use App\Analysis\Support\LinkCheckResult;
use App\Analysis\Support\LinkExtractor;
use App\Analysis\Support\LinkState;
use Illuminate\Support\Str;

/**
 * Internal/external links, links without text and broken links. Only the
 * first seo-audit.links.max_checked unique URLs are requested (internal ones
 * first), and the report says how many were left unchecked.
 */
final class LinksAnalyzer implements Analyzer
{
    public function __construct(
        private readonly LinkExtractor $extractor,
        private readonly LinkChecker $checker,
    ) {}

    public function analyze(AuditContext $context): SectionResult
    {
        $links = $this->extractor->extract($context->document(), $context->page->finalUrl);
        $unique = $this->uniqueByUrl($links);
        $internal = array_filter($unique, fn (ExtractedLink $link) => $link->isInternal);
        $external = array_filter($unique, fn (ExtractedLink $link) => ! $link->isInternal);
        $withoutText = count(array_filter($links, fn (ExtractedLink $link) => $link->text === ''));
        $nofollow = count(array_filter($links, fn (ExtractedLink $link) => $link->isNofollow));

        $limit = max(0, (int) config('seo-audit.links.max_checked'));
        $toCheck = array_slice([...array_values($internal), ...array_values($external)], 0, $limit);
        $results = $this->checker->check(array_map(fn (ExtractedLink $link) => $link->url, $toCheck));

        $broken = [];
        $restricted = 0;
        $skipped = 0;

        foreach ($toCheck as $link) {
            $result = $results[$link->url] ?? LinkCheckResult::skipped();

            match ($result->state) {
                LinkState::Broken => $broken[] = $this->brokenLinkRecord($link, $result),
                LinkState::Restricted => $restricted++,
                LinkState::Skipped => $skipped++,
                LinkState::Ok => null,
            };
        }

        $brokenInternal = array_values(array_filter($broken, fn (array $link) => $link['is_internal']));
        $brokenExternal = array_values(array_filter($broken, fn (array $link) => ! $link['is_internal']));
        $unchecked = count($unique) - count($toCheck);

        $checks = [
            new Check('total_links', 'Enlaces en la página', CheckStatus::Info, count($links).' ('.count($unique).' URL distintas)'),
            new Check('internal_links', 'Enlaces internos', $internal === [] ? CheckStatus::Warning : CheckStatus::Pass, (string) count($internal)),
            new Check('external_links', 'Enlaces externos', CheckStatus::Info, (string) count($external)),
            new Check('broken_links', 'Enlaces rotos', $broken === [] ? CheckStatus::Pass : CheckStatus::Fail, count($broken).' de '.count($toCheck).' comprobados'),
            new Check('links_without_text', 'Enlaces sin texto', $withoutText === 0 ? CheckStatus::Pass : CheckStatus::Warning, (string) $withoutText),
        ];

        if ($unchecked > 0) {
            $checks[] = new Check('unchecked_links', 'Enlaces no comprobados (límite)', CheckStatus::Info, (string) $unchecked);
        }

        $issues = [];

        if ($brokenInternal !== []) {
            $issues[] = Issue::high(
                'broken_internal_links',
                'Hay enlaces internos rotos',
                'Corrige o elimina los enlaces que apuntan a páginas inexistentes de tu propio sitio. Si una página se ha movido, redirígela con un 301 y actualiza el enlace.',
                $this->evidence($brokenInternal),
            );
        }

        if ($brokenExternal !== []) {
            $issues[] = Issue::medium(
                'broken_external_links',
                'Hay enlaces externos rotos',
                'Actualiza o elimina los enlaces a otros sitios que ya no funcionan: empeoran la experiencia de usuario y la confianza en la página.',
                $this->evidence($brokenExternal),
            );
        }

        if ($internal === []) {
            $issues[] = Issue::low(
                'no_internal_links',
                'La página no enlaza a otras páginas del sitio',
                'Añade enlaces internos relevantes (menú, artículos relacionados, migas de pan) para que usuarios y buscadores descubran el resto del sitio.',
            );
        }

        if ($withoutText > 0) {
            $issues[] = Issue::low(
                'links_without_text',
                'Hay enlaces sin texto',
                'Añade texto descriptivo a cada enlace, o un alt a la imagen enlazada / un aria-label. Ayuda a lectores de pantalla y buscadores a saber a dónde lleva.',
                "{$withoutText} enlace(s) no tienen texto accesible.",
            );
        }

        return SectionResult::completed($checks, $issues, [
            'totals' => [
                'links' => count($links),
                'unique' => count($unique),
                'internal' => count($internal),
                'external' => count($external),
                'nofollow' => $nofollow,
                'without_text' => $withoutText,
            ],
            'checked' => [
                'limit' => $limit,
                'checked' => count($toCheck),
                'broken' => count($broken),
                'restricted' => $restricted,
                'skipped' => $skipped,
                'unchecked' => $unchecked,
            ],
        ], brokenLinks: $broken);
    }

    /**
     * @param  list<ExtractedLink>  $links
     * @return array<string, ExtractedLink>
     */
    private function uniqueByUrl(array $links): array
    {
        $unique = [];

        foreach ($links as $link) {
            // Keep the first occurrence, but prefer one that has visible text.
            if (! isset($unique[$link->url]) || ($unique[$link->url]->text === '' && $link->text !== '')) {
                $unique[$link->url] = $link;
            }
        }

        return $unique;
    }

    /**
     * @return array{url: string, is_internal: bool, status_code: int|null, error: string|null, link_text: string|null}
     */
    private function brokenLinkRecord(ExtractedLink $link, LinkCheckResult $result): array
    {
        return [
            'url' => $link->url,
            'is_internal' => $link->isInternal,
            'status_code' => $result->statusCode,
            'error' => $result->error,
            'link_text' => $link->text === '' ? null : Str::limit($link->text, 250),
        ];
    }

    /**
     * @param  list<array{url: string, status_code: int|null, error: string|null}>  $links
     */
    private function evidence(array $links): string
    {
        $examples = array_map(
            fn (array $link) => Str::limit($link['url'], 90).' ('.($link['status_code'] ?? 'sin respuesta').')',
            array_slice($links, 0, 3),
        );

        return count($links).' enlace(s) con error: '.implode(' · ', $examples).(count($links) > 3 ? '…' : '.');
    }
}
