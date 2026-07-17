<?php

namespace App\Jobs;

use App\Models\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\DomCrawler\Crawler;

class AnalyzeMetaTagsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Audit $audit
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $html = Cache::get("audit:{$this->audit->id}:html");

        if (! $html) {
            $this->audit->results()->create([
                'type' => 'meta',
                'data' => ['error' => 'html_not_found_in_cache'],
            ]);
            return;
        }

        $crawler = new Crawler($html);

        $title = $this->extractTitle($crawler);
        $metaDescription = $this->extractMeta($crawler, 'description');
        $canonical = $this->extractCanonical($crawler);
        $robots = $this->extractMeta($crawler, 'robots');
        $ogTitle = $this->extractMeta($crawler, 'og:title', 'property');
        $ogDescription = $this->extractMeta($crawler, 'og:description', 'property');

        $issues = $this->detectIssues($title, $metaDescription, $canonical);

        $this->audit->results()->create([
            'type' => 'meta',
            'data' => [
                'title' => $title,
                'title_length' => $title ? strlen($title) : 0,
                'meta_description' => $metaDescription,
                'meta_description_length' => $metaDescription ? strlen($metaDescription) : 0,
                'canonical' => $canonical,
                'robots' => $robots,
                'og_title' => $ogTitle,
                'og_description' => $ogDescription,
                'issues' => $issues,
            ],
            'score' => $this->calculateScore($issues),
        ]);
    }

    private function extractTitle(Crawler $crawler): ?string
    {
        $titleNode = $crawler->filter('title');

        return $titleNode->count() > 0 ? trim($titleNode->text()) : null;
    }

    private function extractMeta(Crawler $crawler, string $name, string $attribute = 'name'): ?string
    {
        $node = $crawler->filter("meta[{$attribute}=\"{$name}\"]");

        return $node->count() > 0 ? $node->attr('content') : null;
    }

    private function extractCanonical(Crawler $crawler): ?string
    {
        $node = $crawler->filter('link[rel="canonical"]');

        return $node->count() > 0 ? $node->attr('href') : null;
    }

    private function detectIssues(?string $title, ?string $metaDescription, ?string $canonical): array
    {
        $issues = [];

        if (! $title) {
            $issues[] = 'missing_title';
        } elseif (strlen($title) > 60) {
            $issues[] = 'title_too_long';
        } elseif (strlen($title) < 30) {
            $issues[] = 'title_too_short';
        }

        if (! $metaDescription) {
            $issues[] = 'missing_meta_description';
        } elseif (strlen($metaDescription) > 160) {
            $issues[] = 'meta_description_too_long';
        }

        if (! $canonical) {
            $issues[] = 'missing_canonical';
        }

        return $issues;
    }

    private function calculateScore(array $issues): int
    {
        return max(0, 100 - (count($issues) * 15));
    }
}