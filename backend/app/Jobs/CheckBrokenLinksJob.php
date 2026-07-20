<?php

namespace App\Jobs;

use App\Models\Audit;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

class CheckBrokenLinksJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_LINKS = 50;
    private const TIMEOUT_SECONDS = 10;

    /**
     * Este job hace hasta 100 peticiones HTTP, así que le damos más margen
     * que el timeout por defecto del worker (60s).
     */
    public int $timeout = 300;

    public function __construct(
        public Audit $audit
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $html = Cache::get("audit:{$this->audit->id}:html");

        if (! $html) {
            $this->audit->results()->create([
                'type' => 'links',
                'data' => ['error' => 'html_not_found_in_cache'],
                'score' => 0,
            ]);
            return;
        }

        $links = $this->extractLinks($html);
        $results = $this->checkLinks(array_keys($links));

        $brokenCount = 0;

        foreach ($results as $url => $statusCode) {
            // Un enlace se considera roto si respondió con error (>= 400)
            // o si no respondió en absoluto (null).
            if ($statusCode === null || $statusCode >= 400) {
                $brokenCount++;

                $this->audit->brokenLinks()->create([
                    'url' => $url,
                    'status_code' => $statusCode,
                    'link_text' => $links[$url],
                ]);
            }
        }

        $totalChecked = count($results);

        $this->audit->results()->create([
            'type' => 'links',
            'data' => [
                'total_links' => $totalChecked,
                'broken_count' => $brokenCount,
                'issues' => $brokenCount > 0 ? ['broken_links_found'] : [],
            ],
            'score' => $this->calculateScore($totalChecked, $brokenCount),
        ]);
    }

    /**
     * Devuelve un array [url_absoluta => texto_del_enlace], deduplicado
     * y limitado a MAX_LINKS.
     */
    private function extractLinks(string $html): array
    {
        $crawler = new Crawler($html);
        $baseUrl = $this->audit->url;

        $links = [];

        foreach ($crawler->filter('a[href]') as $node) {
            $href = trim($node->getAttribute('href'));

            // Ignoramos anclas internas y esquemas no navegables (mailto:, tel:, javascript:).
            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }

            $absolute = UriResolver::resolve($href, $baseUrl);

            if (! preg_match('#^https?://#i', $absolute)) {
                continue;
            }

            // Quitamos el fragmento: https://x.com/page#seccion apunta al mismo recurso.
            $absolute = preg_replace('/#.*$/', '', $absolute);

            if (! isset($links[$absolute])) {
                $links[$absolute] = trim(preg_replace('/\s+/', ' ', $node->textContent));
            }

            if (count($links) >= self::MAX_LINKS) {
                break;
            }
        }

        return $links;
    }

    /**
     * Comprueba los enlaces en paralelo. Devuelve [url => status_code|null].
     */
    private function checkLinks(array $urls): array
    {
        if (empty($urls)) {
            return [];
        }

        $responses = $this->poolRequests($urls, 'head');

        $results = [];
        $retryWithGet = [];

        foreach ($urls as $url) {
            $response = $responses[$url] ?? null;

            if ($response instanceof Response) {
                $status = $response->status();

                // 405 (Method Not Allowed) o 501: el servidor no soporta HEAD,
                // lo reintentamos con GET antes de darlo por roto.
                if (in_array($status, [405, 501], true)) {
                    $retryWithGet[] = $url;
                } else {
                    $results[$url] = $status;
                }
            } else {
                // ConnectionException u otro fallo: sin respuesta.
                $results[$url] = null;
            }
        }

        if (! empty($retryWithGet)) {
            $getResponses = $this->poolRequests($retryWithGet, 'get');

            foreach ($retryWithGet as $url) {
                $response = $getResponses[$url] ?? null;
                $results[$url] = $response instanceof Response ? $response->status() : null;
            }
        }

        return $results;
    }

    private function poolRequests(array $urls, string $method): array
    {
        return Http::pool(function (Pool $pool) use ($urls, $method) {
            foreach ($urls as $url) {
                $pool->as($url)
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->withUserAgent('SEO-Audit-Tool/1.0')
                    ->{$method}($url);
            }
        });
    }

    private function calculateScore(int $total, int $broken): int
    {
        if ($total === 0 || $broken === 0) {
            return 100;
        }

        // Penalización proporcional al porcentaje de enlaces rotos.
        return max(0, 100 - (int) round(($broken / $total) * 100));
    }
}
