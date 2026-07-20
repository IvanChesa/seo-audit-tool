<?php

namespace App\Jobs;

use App\Models\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Bus\Batch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Throwable;

class FetchPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 5;

    public function __construct(
        public Audit $audit
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->audit->update(['status' => 'processing']);

        try {
            $response = Http::timeout(15)
                ->withUserAgent('SEO-Audit-Tool/1.0')
                ->get($this->audit->url);

            $html = $response->body();
            $statusCode = $response->status();

            // Guardamos el HTML temporalmente en caché para que
            // los siguientes Jobs (meta tags, encabezados, etc.) lo usen
            // sin tener que descargar la página otra vez.
            Cache::put("audit:{$this->audit->id}:html", $html, now()->addMinutes(30));

            $this->audit->results()->create([
                'type' => 'fetch',
                'data' => [
                    'status_code' => $statusCode,
                    'content_length' => strlen($html),
                    'success' => $response->successful(),
                ],
            ]);

            if (! $response->successful()) {
                $this->audit->update(['status' => 'failed']);
                return;
            }

            $this->dispatchAnalysisBatch();

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->audit->results()->create([
                'type' => 'fetch',
                'data' => [
                    'error' => 'connection_failed',
                    'message' => $e->getMessage(),
                ],
            ]);

            $this->audit->update(['status' => 'failed']);
        }
    }

    /**
     * Despacha los 4 jobs de análisis en paralelo dentro de un batch.
     * Cuando todos terminan, then() calcula el score global; si alguno
     * falla, catch() marca la auditoría como fallida.
     *
     * Importante: los callbacks se serializan y se ejecutan más tarde en
     * el worker, por eso capturamos el ID y no el modelo completo.
     */
    private function dispatchAnalysisBatch(): void
    {
        $auditId = $this->audit->id;

        Bus::batch([
            new AnalyzeMetaTagsJob($this->audit),
            new AnalyzeHeadingsJob($this->audit),
            new AnalyzeKeywordDensityJob($this->audit),
            new CheckBrokenLinksJob($this->audit),
            new PageSpeedJob($this->audit),
        ])
            ->name("audit:{$auditId}")
            ->then(function (Batch $batch) use ($auditId) {
                $audit = Audit::find($auditId);

                if (! $audit) {
                    return;
                }

                $audit->update([
                    'score' => self::calculateGlobalScore($audit),
                    'status' => 'completed',
                ]);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($auditId) {
                Audit::where('id', $auditId)->update(['status' => 'failed']);
            })
            ->dispatch();
    }

    /**
     * Media ponderada de los scores por tipo de análisis. Si un tipo no
     * tiene score (ej. speed sin API key), se reparte su peso entre los
     * demás en lugar de contarlo como 0.
     */
    public static function calculateGlobalScore(Audit $audit): int
    {
        $weights = [
            'meta' => 0.25,
            'headings' => 0.20,
            'keywords' => 0.15,
            'links' => 0.25,
            'speed' => 0.15,
        ];

        $scores = $audit->results()
            ->whereIn('type', array_keys($weights))
            ->whereNotNull('score')
            ->pluck('score', 'type');

        $weightedSum = 0;
        $totalWeight = 0;

        foreach ($weights as $type => $weight) {
            if (isset($scores[$type])) {
                $weightedSum += $scores[$type] * $weight;
                $totalWeight += $weight;
            }
        }

        return $totalWeight > 0 ? (int) round($weightedSum / $totalWeight) : 0;
    }
}