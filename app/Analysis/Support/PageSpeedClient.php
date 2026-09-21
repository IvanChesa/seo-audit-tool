<?php

namespace App\Analysis\Support;

use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google PageSpeed Insights API (Lighthouse lab data).
 *
 * The API key is sent in the X-Goog-Api-Key header instead of the query
 * string, so it never appears in URLs, exception messages or logs.
 */
final class PageSpeedClient
{
    public const API_URL = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /** Lighthouse audits reported, with their field name in our report. */
    public const METRICS = [
        'lcp' => 'largest-contentful-paint',
        'cls' => 'cumulative-layout-shift',
        'fcp' => 'first-contentful-paint',
        'tbt' => 'total-blocking-time',
        'speed_index' => 'speed-index',
    ];

    public function __construct(
        #[Config('services.pagespeed.key')]
        private readonly ?string $apiKey,
        #[Config('seo-audit.pagespeed.strategy')]
        private readonly string $strategy,
        #[Config('seo-audit.pagespeed.timeout')]
        private readonly int $timeout,
    ) {}

    public function isConfigured(): bool
    {
        return is_string($this->apiKey) && trim($this->apiKey) !== '';
    }

    public function strategy(): string
    {
        return $this->strategy === 'desktop' ? 'desktop' : 'mobile';
    }

    /**
     * @return array{performance_score: int|null, metrics: array<string, array{value: float|null, display: string|null}>}
     *
     * @throws PageSpeedException
     */
    public function run(string $url): array
    {
        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-Goog-Api-Key' => (string) $this->apiKey])
                ->timeout($this->timeout)
                ->get(self::API_URL, [
                    'url' => $url,
                    'category' => 'performance',
                    'strategy' => $this->strategy(),
                    'locale' => 'es',
                ]);
        } catch (ConnectionException) {
            throw new PageSpeedException('pagespeed_unavailable', 'PageSpeed Insights no respondió a tiempo.', true);
        }

        if ($response->status() === 429) {
            throw new PageSpeedException('pagespeed_quota', 'Se ha agotado la cuota de la API de PageSpeed Insights. Inténtalo más tarde.', false);
        }

        if ($response->serverError()) {
            throw new PageSpeedException('pagespeed_unavailable', 'PageSpeed Insights no pudo analizar la página (error '.$response->status().').', true);
        }

        if ($response->failed()) {
            throw new PageSpeedException('pagespeed_rejected', 'PageSpeed Insights rechazó la solicitud (HTTP '.$response->status().'). Revisa que la clave de la API sea válida.', false);
        }

        $lighthouse = $response->json('lighthouseResult');

        if (! is_array($lighthouse)) {
            throw new PageSpeedException('pagespeed_invalid_response', 'PageSpeed Insights devolvió una respuesta incompleta.', true);
        }

        $score = $lighthouse['categories']['performance']['score'] ?? null;
        $metrics = [];

        foreach (self::METRICS as $key => $audit) {
            $metric = $lighthouse['audits'][$audit] ?? null;
            $metrics[$key] = [
                'value' => is_numeric($metric['numericValue'] ?? null) ? (float) $metric['numericValue'] : null,
                'display' => is_string($metric['displayValue'] ?? null) ? $metric['displayValue'] : null,
            ];
        }

        return [
            'performance_score' => is_numeric($score) ? (int) round((float) $score * 100) : null,
            'metrics' => $metrics,
        ];
    }
}
