<?php

namespace App\Jobs;

use App\Models\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

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

            // TODO(tarea 5): sustituir por Bus::batch con los 4 jobs de análisis.
            AnalyzeMetaTagsJob::dispatch($this->audit);
            AnalyzeHeadingsJob::dispatch($this->audit);
            AnalyzeKeywordDensityJob::dispatch($this->audit);
            CheckBrokenLinksJob::dispatch($this->audit);
            PageSpeedJob::dispatch($this->audit);

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
}