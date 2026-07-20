<?php

namespace App\Jobs;

use App\Models\Audit;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\DomCrawler\Crawler;

class AnalyzeHeadingsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Audit $audit
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Si el batch fue cancelado (otro job falló), no hacemos trabajo inútil.
        if ($this->batch()?->cancelled()) {
            return;
        }

        $html = Cache::get("audit:{$this->audit->id}:html");

        if (! $html) {
            $this->audit->results()->create([
                'type' => 'headings',
                'data' => ['error' => 'html_not_found_in_cache'],
                'score' => 0,
            ]);
            return;
        }

        $crawler = new Crawler($html);

        // Recorremos los encabezados en orden de aparición en el documento.
        $headings = $crawler->filter('h1, h2, h3, h4, h5, h6')->each(function (Crawler $node) {
            return [
                'level' => (int) substr($node->nodeName(), 1),
                'text' => trim(preg_replace('/\s+/', ' ', $node->text())),
            ];
        });

        $issues = $this->detectIssues($headings);

        $this->audit->results()->create([
            'type' => 'headings',
            'data' => [
                'headings' => $headings,
                'counts' => $this->countByLevel($headings),
                'issues' => $issues,
            ],
            'score' => $this->calculateScore($issues),
        ]);
    }

    private function countByLevel(array $headings): array
    {
        $counts = ['h1' => 0, 'h2' => 0, 'h3' => 0, 'h4' => 0, 'h5' => 0, 'h6' => 0];

        foreach ($headings as $heading) {
            $counts['h'.$heading['level']]++;
        }

        return $counts;
    }

    private function detectIssues(array $headings): array
    {
        $issues = [];

        $h1Count = count(array_filter($headings, fn ($h) => $h['level'] === 1));

        if ($h1Count === 0) {
            $issues[] = 'missing_h1';
        } elseif ($h1Count > 1) {
            $issues[] = 'multiple_h1';
        }

        // Un "salto" es pasar de un nivel a otro más profundo saltándose
        // niveles intermedios (ej. H1 -> H3 sin H2). Subir de nivel es válido.
        $previousLevel = null;

        foreach ($headings as $heading) {
            if ($previousLevel !== null && $heading['level'] > $previousLevel + 1) {
                $issues[] = 'skipped_heading_level';
                break;
            }
            $previousLevel = $heading['level'];
        }

        return $issues;
    }

    private function calculateScore(array $issues): int
    {
        $penalties = [
            'missing_h1' => 40,
            'multiple_h1' => 20,
            'skipped_heading_level' => 15,
        ];

        $score = 100;

        foreach ($issues as $issue) {
            $score -= $penalties[$issue] ?? 10;
        }

        return max(0, $score);
    }
}
