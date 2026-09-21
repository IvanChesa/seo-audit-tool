<?php

namespace App\Analysis\Analyzers;

use App\Analysis\AuditContext;
use App\Analysis\Check;
use App\Analysis\CheckStatus;
use App\Analysis\Contracts\Analyzer;
use App\Analysis\HtmlDocument;
use App\Analysis\Issue;
use App\Analysis\PageSnapshot;
use App\Analysis\SectionResult;
use App\Analysis\Support\CrawlFilesInspector;

/**
 * HTTP status, HTTPS, redirects, response time, document language, mobile
 * viewport, robots.txt (including whether it blocks this page) and sitemap.
 */
final class TechnicalAnalyzer implements Analyzer
{
    /** Download time of the HTML above which the server is considered slow. */
    public const SLOW_RESPONSE_MS = 3000;

    /** @var list<Check> */
    private array $checks = [];

    /** @var list<Issue> */
    private array $issues = [];

    public function __construct(private readonly CrawlFilesInspector $crawlFiles) {}

    public function analyze(AuditContext $context): SectionResult
    {
        $this->checks = [];
        $this->issues = [];
        $page = $context->page;
        $document = $context->document();

        $this->checks[] = new Check('http_status', 'Código de estado HTTP', CheckStatus::Pass, (string) $page->statusCode);
        $https = $this->analyzeHttps($page);
        $this->analyzeRedirects($page);
        $this->analyzeResponse($page);
        $lang = $this->analyzeLang($document);
        $viewport = $this->analyzeViewport($document);
        [$robots, $sitemap] = $this->analyzeCrawlFiles($page);

        return SectionResult::completed($this->checks, $this->issues, [
            'final_url' => $page->finalUrl,
            'status_code' => $page->statusCode,
            'https' => $https,
            'redirects' => $page->redirects,
            'response_time_ms' => $page->responseTimeMs,
            'page_bytes' => $page->bytes(),
            'lang' => $lang,
            'viewport' => $viewport,
            'robots_txt' => $robots,
            'sitemap' => $sitemap,
        ]);
    }

    private function analyzeHttps(PageSnapshot $page): bool
    {
        $https = str_starts_with(strtolower($page->finalUrl), 'https://');

        if ($https) {
            $this->checks[] = new Check('https', 'HTTPS', CheckStatus::Pass, 'Sí');

            return true;
        }

        $this->checks[] = new Check('https', 'HTTPS', CheckStatus::Fail, 'No');
        $this->issues[] = Issue::high(
            'no_https',
            'La página no usa HTTPS',
            'Instala un certificado TLS (p. ej. gratuito con Let\'s Encrypt), sirve la web por HTTPS y redirige todo el tráfico HTTP con un 301. HTTPS es una señal de posicionamiento y los navegadores marcan las páginas HTTP como «No seguro».',
            "La URL final es {$page->finalUrl}.",
        );

        return false;
    }

    private function analyzeRedirects(PageSnapshot $page): void
    {
        $count = count($page->redirects);

        if ($count === 0) {
            $this->checks[] = new Check('redirects', 'Redirecciones', CheckStatus::Pass, 'Ninguna');

            return;
        }

        $chain = implode(' → ', [
            ...array_map(fn (array $hop) => "{$hop['url']} ({$hop['status']})", $page->redirects),
            $page->finalUrl,
        ]);

        if ($count === 1) {
            $this->checks[] = new Check('redirects', 'Redirecciones', CheckStatus::Pass, "1 ({$page->redirects[0]['status']})");

            return;
        }

        $this->checks[] = new Check('redirects', 'Redirecciones', CheckStatus::Warning, "{$count} encadenadas");
        $this->issues[] = Issue::low(
            'redirect_chain',
            'La URL pasa por una cadena de redirecciones',
            'Redirige directamente a la URL final con un único 301 y actualiza los enlaces para que apunten a ella: cada salto añade latencia.',
            $chain,
        );
    }

    private function analyzeResponse(PageSnapshot $page): void
    {
        $milliseconds = $page->responseTimeMs;
        $slow = $milliseconds > self::SLOW_RESPONSE_MS;

        $this->checks[] = new Check('response_time', 'Tiempo de descarga del HTML', $slow ? CheckStatus::Warning : CheckStatus::Info, "{$milliseconds} ms");
        $this->checks[] = new Check('page_size', 'Tamaño del HTML', CheckStatus::Info, number_format($page->bytes() / 1024, 1, ',', '.').' KB');

        if ($slow) {
            $this->issues[] = Issue::low(
                'slow_server_response',
                'El servidor tarda en entregar la página',
                'Revisa la caché del servidor, el alojamiento o el uso de un CDN. Un HTML que tarda varios segundos en llegar retrasa todo lo demás.',
                "El HTML tardó {$milliseconds} ms en descargarse (medido desde el servidor de esta herramienta; puede variar según la ubicación).",
            );
        }
    }

    private function analyzeLang(HtmlDocument $document): ?string
    {
        $lang = $document->htmlAttribute('lang');

        if ($lang === null || $lang === '') {
            $this->checks[] = new Check('lang', 'Idioma del documento', CheckStatus::Fail, 'No indicado');
            $this->issues[] = Issue::medium(
                'missing_lang',
                'No se indica el idioma del documento',
                'Añade el atributo lang a la etiqueta <html> (por ejemplo <html lang="es">). Lo usan los buscadores, los traductores automáticos y los lectores de pantalla.',
            );

            return null;
        }

        if (preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/i', $lang) !== 1) {
            $this->checks[] = new Check('lang', 'Idioma del documento', CheckStatus::Warning, $lang);
            $this->issues[] = Issue::low(
                'invalid_lang',
                'El código de idioma no es válido',
                'Usa un código de idioma BCP 47 como "es", "es-ES" o "en".',
                "Valor encontrado: lang=\"{$lang}\".",
            );

            return $lang;
        }

        $this->checks[] = new Check('lang', 'Idioma del documento', CheckStatus::Pass, $lang);

        return $lang;
    }

    private function analyzeViewport(HtmlDocument $document): ?string
    {
        $viewport = $document->metaContent('viewport');

        if ($viewport === null) {
            $this->checks[] = new Check('viewport', 'Viewport para móviles', CheckStatus::Fail, 'No definido');
            $this->issues[] = Issue::high(
                'missing_viewport',
                'Falta la etiqueta viewport',
                'Añade <meta name="viewport" content="width=device-width, initial-scale=1"> para que la página se adapte a las pantallas móviles. Google indexa primero la versión móvil.',
            );

            return null;
        }

        $normalized = strtolower(str_replace(' ', '', $viewport));

        if (! str_contains($normalized, 'width=device-width')) {
            $this->checks[] = new Check('viewport', 'Viewport para móviles', CheckStatus::Warning, $viewport);
            $this->issues[] = Issue::medium(
                'viewport_not_responsive',
                'El viewport no se adapta al ancho del dispositivo',
                'Incluye width=device-width en la etiqueta viewport para que el ancho de la página se ajuste a la pantalla.',
                "content=\"{$viewport}\"",
            );

            return $viewport;
        }

        $blocksZoom = preg_match('/user-scalable=(no|0)(,|$)/', $normalized) === 1
            || (preg_match('/maximum-scale=([\d.]+)/', $normalized, $match) === 1 && (float) $match[1] < 2);

        if ($blocksZoom) {
            $this->checks[] = new Check('viewport', 'Viewport para móviles', CheckStatus::Warning, $viewport);
            $this->issues[] = Issue::low(
                'viewport_blocks_zoom',
                'La página impide ampliar el contenido',
                'Elimina user-scalable=no y maximum-scale del viewport: bloquear el zoom dificulta la lectura a personas con baja visión.',
                "content=\"{$viewport}\"",
            );

            return $viewport;
        }

        $this->checks[] = new Check('viewport', 'Viewport para móviles', CheckStatus::Pass, $viewport);

        return $viewport;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function analyzeCrawlFiles(PageSnapshot $page): array
    {
        $parts = parse_url($page->finalUrl);
        $origin = strtolower(($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '')).(isset($parts['port']) ? ':'.$parts['port'] : '');
        $pathAndQuery = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        $robots = $this->crawlFiles->robotsTxt($origin);
        $rules = $robots['robots'];
        $rule = $rules?->decisiveRule($pathAndQuery);
        $blocked = $rule !== null && $rule['type'] === 'disallow';

        if ($robots['status'] === CrawlFilesInspector::FOUND) {
            $this->checks[] = new Check('robots_txt', 'robots.txt', $blocked ? CheckStatus::Fail : CheckStatus::Pass, $blocked ? 'Bloquea esta página' : 'Encontrado');
        } elseif ($robots['status'] === CrawlFilesInspector::MISSING) {
            $this->checks[] = new Check('robots_txt', 'robots.txt', CheckStatus::Warning, 'No encontrado');
            $this->issues[] = Issue::low(
                'missing_robots_txt',
                'No hay archivo robots.txt',
                'Crea un /robots.txt, aunque solo contenga «User-agent: *», «Allow: /» y la línea «Sitemap:» con la URL del sitemap. Indica a los buscadores qué pueden rastrear y dónde está el sitemap.',
                $robots['http_status'] !== null ? "{$robots['url']} respondió con HTTP {$robots['http_status']}." : null,
            );
        } else {
            $this->checks[] = new Check('robots_txt', 'robots.txt', CheckStatus::Unknown, 'No se pudo comprobar');
        }

        if ($blocked) {
            $this->issues[] = Issue::critical(
                'blocked_by_robots_txt',
                'robots.txt impide rastrear esta página',
                'Revisa las reglas Disallow de robots.txt: tal como están, los buscadores no pueden rastrear esta URL. Si no es intencionado, elimina o ajusta la regla.',
                "Regla aplicada: «Disallow: {$rule['path']}» (grupo User-agent: {$rule['agent']}).",
            );
        }

        $sitemap = $this->crawlFiles->sitemap($origin, $rules === null ? [] : $rules->sitemaps);

        if ($sitemap['status'] === CrawlFilesInspector::FOUND) {
            $this->checks[] = new Check('sitemap', 'Sitemap XML', CheckStatus::Pass, $sitemap['url']);
        } elseif ($sitemap['status'] === CrawlFilesInspector::MISSING) {
            $this->checks[] = new Check('sitemap', 'Sitemap XML', CheckStatus::Warning, 'No encontrado');
            $this->issues[] = $sitemap['source'] === 'robots_txt'
                ? Issue::low(
                    'sitemap_not_found',
                    'El sitemap declarado en robots.txt no está disponible',
                    'Corrige la línea «Sitemap:» de robots.txt para que apunte a un sitemap XML accesible.',
                    "{$sitemap['url']} respondió con HTTP ".($sitemap['http_status'] ?? '—').'.',
                )
                : Issue::low(
                    'missing_sitemap',
                    'No se ha encontrado un sitemap XML',
                    'Genera un sitemap.xml con las URL indexables (la mayoría de CMS lo hacen automáticamente), decláralo en robots.txt con «Sitemap: https://…/sitemap.xml» y envíalo en Google Search Console.',
                    'robots.txt no declara ningún sitemap y no existen /sitemap.xml ni /sitemap_index.xml.',
                );
        } else {
            $this->checks[] = new Check('sitemap', 'Sitemap XML', CheckStatus::Unknown, 'No se pudo comprobar');
        }

        return [
            [
                'url' => $robots['url'],
                'status' => $robots['status'],
                'http_status' => $robots['http_status'],
                'blocks_page' => $robots['status'] === CrawlFilesInspector::FOUND ? $blocked : null,
                'sitemaps' => $rules === null ? [] : $rules->sitemaps,
            ],
            $sitemap,
        ];
    }
}
