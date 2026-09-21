<?php

namespace App\Analysis\Analyzers;

use App\Analysis\AuditContext;
use App\Analysis\Check;
use App\Analysis\CheckStatus;
use App\Analysis\Contracts\Analyzer;
use App\Analysis\HtmlDocument;
use App\Analysis\Issue;
use App\Analysis\SectionResult;
use App\Analysis\Support\RobotsDirectives;
use App\Analysis\Support\UrlComparison;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\UriResolver;

/**
 * <title>, meta description, canonical, robots directives and Open Graph.
 * Lengths are measured in characters (not bytes), so accents count once.
 */
final class MetaAnalyzer implements Analyzer
{
    public const TITLE_MIN = 30;

    public const TITLE_MAX = 60;

    public const DESCRIPTION_MIN = 70;

    public const DESCRIPTION_MAX = 160;

    private const OPEN_GRAPH_REQUIRED = ['og:title', 'og:description', 'og:image'];

    private const OPEN_GRAPH_OPTIONAL = ['og:url', 'og:type'];

    /** @var list<Check> */
    private array $checks = [];

    /** @var list<Issue> */
    private array $issues = [];

    public function analyze(AuditContext $context): SectionResult
    {
        $this->checks = [];
        $this->issues = [];
        $document = $context->document();

        $title = $this->analyzeTitle($document);
        $description = $this->analyzeDescription($document);
        $canonical = $this->analyzeCanonical($document, $context->page->finalUrl);
        $robots = $this->analyzeRobots($document, $context->page->xRobotsTag);
        $openGraph = $this->analyzeOpenGraph($document);

        return SectionResult::completed($this->checks, $this->issues, [
            'title' => $title,
            'title_length' => $title === null ? 0 : mb_strlen($title),
            'meta_description' => $description,
            'meta_description_length' => $description === null ? 0 : mb_strlen($description),
            'canonical' => $canonical,
            'robots' => $robots,
            'open_graph' => $openGraph,
        ]);
    }

    private function analyzeTitle(HtmlDocument $document): ?string
    {
        $titles = $document->titles();
        $title = $titles[0] ?? null;

        if ($title === null || $title === '') {
            $this->checks[] = new Check('title', 'Título (<title>)', CheckStatus::Fail, 'No encontrado');
            $this->issues[] = Issue::high(
                'missing_title',
                'Falta el título de la página',
                'Añade dentro de <head> una etiqueta <title> única y descriptiva de 30 a 60 caracteres. Es el texto principal del resultado en los buscadores y el nombre de la pestaña del navegador.',
                $title === '' ? 'La etiqueta <title> existe pero está vacía.' : 'No se ha encontrado ninguna etiqueta <title>.',
            );

            return $title === '' ? '' : null;
        }

        $length = mb_strlen($title);
        $status = CheckStatus::Pass;

        if ($length > self::TITLE_MAX) {
            $status = CheckStatus::Warning;
            $this->issues[] = Issue::medium(
                'title_too_long',
                'El título es demasiado largo',
                'Resume el título en 60 caracteres o menos y coloca primero las palabras más importantes: los buscadores cortan los títulos largos.',
                "El título tiene {$length} caracteres: «".Str::limit($title, 120).'».',
            );
        } elseif ($length < self::TITLE_MIN) {
            $status = CheckStatus::Warning;
            $this->issues[] = Issue::low(
                'title_too_short',
                'El título es muy corto',
                'Amplía el título hasta 30–60 caracteres describiendo el contenido de la página y su tema principal.',
                "El título tiene {$length} caracteres: «{$title}».",
            );
        }

        if (count($titles) > 1) {
            $this->issues[] = Issue::low(
                'multiple_titles',
                'Hay varias etiquetas <title>',
                'Deja una sola etiqueta <title> dentro de <head>: los buscadores solo usan una y el resto crea ambigüedad.',
                'Se han encontrado '.count($titles).' etiquetas <title>.',
            );
        }

        $this->checks[] = new Check('title', 'Título (<title>)', $status, "{$length} caracteres");

        return $title;
    }

    private function analyzeDescription(HtmlDocument $document): ?string
    {
        $descriptions = $document->metaContents('description');
        $description = $descriptions[0] ?? null;

        if ($description === null || $description === '') {
            $this->checks[] = new Check('meta_description', 'Meta description', CheckStatus::Fail, 'No encontrada');
            $this->issues[] = Issue::medium(
                'missing_meta_description',
                'Falta la meta description',
                'Añade <meta name="description" content="…"> con un resumen atractivo de 70 a 160 caracteres. Los buscadores suelen mostrarlo bajo el título e influye en cuántas personas hacen clic.',
                $description === '' ? 'La meta description existe pero está vacía.' : 'No se ha encontrado la etiqueta <meta name="description">.',
            );

            return $description === '' ? '' : null;
        }

        $length = mb_strlen($description);
        $status = CheckStatus::Pass;

        if ($length > self::DESCRIPTION_MAX) {
            $status = CheckStatus::Warning;
            $this->issues[] = Issue::low(
                'meta_description_too_long',
                'La meta description es demasiado larga',
                'Reduce la descripción a 160 caracteres o menos para que no se corte en los resultados de búsqueda.',
                "La descripción tiene {$length} caracteres.",
            );
        } elseif ($length < self::DESCRIPTION_MIN) {
            $status = CheckStatus::Warning;
            $this->issues[] = Issue::low(
                'meta_description_too_short',
                'La meta description es muy corta',
                'Amplía la descripción hasta 70–160 caracteres explicando qué encontrará el usuario en la página.',
                "La descripción tiene {$length} caracteres: «{$description}».",
            );
        }

        if (count($descriptions) > 1) {
            $this->issues[] = Issue::low(
                'multiple_meta_descriptions',
                'Hay varias meta descriptions',
                'Deja una sola etiqueta <meta name="description"> por página.',
                'Se han encontrado '.count($descriptions).' etiquetas meta description.',
            );
        }

        $this->checks[] = new Check('meta_description', 'Meta description', $status, "{$length} caracteres");

        return $description;
    }

    private function analyzeCanonical(HtmlDocument $document, string $pageUrl): ?string
    {
        $hrefs = $document->linkHrefs('canonical');

        if ($hrefs === []) {
            $this->checks[] = new Check('canonical', 'URL canónica', CheckStatus::Warning, 'No definida');
            $this->issues[] = Issue::medium(
                'missing_canonical',
                'No hay URL canónica',
                'Añade <link rel="canonical" href="…"> con la URL preferida de la página. Evita que variantes con parámetros, www/sin www o http/https se traten como contenido duplicado.',
            );

            return null;
        }

        if (count($hrefs) > 1) {
            $this->checks[] = new Check('canonical', 'URL canónica', CheckStatus::Fail, count($hrefs).' etiquetas');
            $this->issues[] = Issue::high(
                'multiple_canonicals',
                'Hay varias URL canónicas',
                'Deja una única etiqueta rel="canonical": cuando hay varias, los buscadores pueden ignorarlas todas.',
                'Valores encontrados: '.implode(' · ', array_map(fn (string $href) => Str::limit($href, 100), $hrefs)).'.',
            );

            return $hrefs[0];
        }

        $canonical = $hrefs[0] === '' ? '' : UriResolver::resolve($hrefs[0], $pageUrl);

        if (preg_match('#^https?://[^/]+#i', $canonical) !== 1) {
            $this->checks[] = new Check('canonical', 'URL canónica', CheckStatus::Fail, 'No válida');
            $this->issues[] = Issue::high(
                'invalid_canonical',
                'La URL canónica no es válida',
                'Usa una URL absoluta con http:// o https:// en el atributo href de rel="canonical".',
                'Valor encontrado: «'.Str::limit($hrefs[0], 150).'».',
            );

            return $hrefs[0];
        }

        if (! UrlComparison::same($canonical, $pageUrl)) {
            $this->checks[] = new Check('canonical', 'URL canónica', CheckStatus::Warning, 'Apunta a otra URL');
            $this->issues[] = Issue::low(
                'canonical_points_elsewhere',
                'La URL canónica apunta a otra página',
                'Si esta página debe posicionarse por sí misma, haz que la canónica apunte a su propia URL. Si es intencionado (por ejemplo, una variante con parámetros), puedes ignorar este aviso.',
                "Canónica: {$canonical} · URL analizada: {$pageUrl}",
            );

            return $canonical;
        }

        $this->checks[] = new Check('canonical', 'URL canónica', CheckStatus::Pass, 'Apunta a esta página');

        return $canonical;
    }

    /**
     * @return list<string>
     */
    private function analyzeRobots(HtmlDocument $document, ?string $xRobotsTag): array
    {
        $directives = RobotsDirectives::fromMetaTags([
            ...$document->metaContents('robots'),
            ...$document->metaContents('googlebot'),
        ]);
        $headerDirectives = RobotsDirectives::fromHeader($xRobotsTag);
        $all = array_values(array_unique([...$directives, ...$headerDirectives]));
        $sources = array_filter([
            $directives !== [] ? '<meta name="robots">' : null,
            $headerDirectives !== [] ? 'cabecera X-Robots-Tag' : null,
        ]);
        $evidence = $all === [] ? null : 'Directivas: '.implode(', ', $all).' ('.implode(' y ', $sources).').';

        if (RobotsDirectives::blocksIndexing($all)) {
            $this->checks[] = new Check('robots', 'Meta robots', CheckStatus::Fail, implode(', ', $all));
            $this->issues[] = Issue::critical(
                'noindex',
                'La página pide no aparecer en los buscadores',
                'Elimina la directiva noindex (en <meta name="robots"> o en la cabecera HTTP X-Robots-Tag) si quieres que esta página se indexe.',
                $evidence,
            );
        } elseif (RobotsDirectives::blocksFollowing($all)) {
            $this->checks[] = new Check('robots', 'Meta robots', CheckStatus::Warning, implode(', ', $all));
            $this->issues[] = Issue::medium(
                'nofollow_page',
                'Los buscadores no seguirán los enlaces de la página',
                'Quita nofollow de la meta robots salvo que realmente no quieras que se rastree ningún enlace de esta página.',
                $evidence,
            );
        } else {
            $this->checks[] = new Check('robots', 'Meta robots', CheckStatus::Pass, $all === [] ? 'index, follow (por defecto)' : implode(', ', $all));
        }

        return $all;
    }

    /**
     * @return array<string, string|null>
     */
    private function analyzeOpenGraph(HtmlDocument $document): array
    {
        $values = [];

        foreach ([...self::OPEN_GRAPH_REQUIRED, ...self::OPEN_GRAPH_OPTIONAL] as $property) {
            $value = $document->metaContent($property, 'property') ?? $document->metaContent($property);
            $values[$property] = $value === '' ? null : $value;
        }

        $missing = array_values(array_filter(self::OPEN_GRAPH_REQUIRED, fn (string $property) => $values[$property] === null));
        $present = count(array_filter($values, fn (?string $value) => $value !== null));

        if ($missing !== []) {
            $this->issues[] = Issue::low(
                'incomplete_open_graph',
                'Faltan etiquetas Open Graph',
                'Añade og:title, og:description y og:image para controlar el título, el texto y la imagen que se muestran al compartir la página en redes sociales y aplicaciones de mensajería.',
                'Faltan: '.implode(', ', $missing).'.',
            );
        }

        $this->checks[] = new Check(
            'open_graph',
            'Open Graph',
            $missing === [] ? CheckStatus::Pass : CheckStatus::Warning,
            "{$present} de ".(count(self::OPEN_GRAPH_REQUIRED) + count(self::OPEN_GRAPH_OPTIONAL)).' etiquetas',
        );

        return $values;
    }
}
