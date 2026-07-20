<?php

namespace App\Jobs;

use App\Models\Audit;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class PageSpeedJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const API_URL = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /**
     * PageSpeed Insights puede tardar bastante en analizar una página.
     */
    public int $timeout = 180;

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

        $apiKey = config('services.pagespeed.key');

        // Sin API key guardamos un resultado informativo pero no rompemos
        // la cadena de análisis: el resto de resultados sigue siendo válido.
        if (! $apiKey) {
            $this->audit->results()->create([
                'type' => 'speed',
                'data' => ['error' => 'api_key_missing'],
                'score' => null,
            ]);
            return;
        }

        try {
            $response = Http::timeout(120)->get(self::API_URL, [
                'url' => $this->audit->url,
                'key' => $apiKey,
                'category' => 'performance',
                'strategy' => 'mobile',
            ]);

            if (! $response->successful()) {
                $this->audit->results()->create([
                    'type' => 'speed',
                    'data' => [
                        'error' => 'api_request_failed',
                        'status_code' => $response->status(),
                    ],
                    'score' => null,
                ]);
                return;
            }

            $lighthouse = $response->json('lighthouseResult');
            $audits = $lighthouse['audits'] ?? [];

            $performanceScore = isset($lighthouse['categories']['performance']['score'])
                ? (int) round($lighthouse['categories']['performance']['score'] * 100)
                : null;

            $this->audit->results()->create([
                'type' => 'speed',
                'data' => [
                    'performance_score' => $performanceScore,
                    'lcp' => $this->extractMetric($audits, 'largest-contentful-paint'),
                    'cls' => $this->extractMetric($audits, 'cumulative-layout-shift'),
                    'fcp' => $this->extractMetric($audits, 'first-contentful-paint'),
                    'strategy' => 'mobile',
                ],
                'score' => $performanceScore,
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->audit->results()->create([
                'type' => 'speed',
                'data' => [
                    'error' => 'connection_failed',
                    'message' => $e->getMessage(),
                ],
                'score' => null,
            ]);
        }
    }

    /**
     * Devuelve valor numérico y texto legible de una métrica de Lighthouse.
     */
    private function extractMetric(array $audits, string $key): ?array
    {
        if (! isset($audits[$key])) {
            return null;
        }

        return [
            'value' => $audits[$key]['numericValue'] ?? null,
            'display' => $audits[$key]['displayValue'] ?? null,
        ];
    }
}
