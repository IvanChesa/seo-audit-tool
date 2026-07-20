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

class AnalyzeKeywordDensityJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const KEYWORD_STUFFING_THRESHOLD = 5.0; // % de densidad

    private const STOPWORDS = [
        // Español
        'a', 'al', 'algo', 'ante', 'antes', 'aquel', 'aquella', 'aquellas', 'aquellos', 'aqui', 'aquí',
        'asi', 'así', 'como', 'cómo', 'con', 'contra', 'cual', 'cuál', 'cuando', 'cuándo', 'de', 'del',
        'desde', 'donde', 'dónde', 'durante', 'e', 'el', 'él', 'ella', 'ellas', 'ellos', 'en', 'entre',
        'era', 'eran', 'es', 'esa', 'esas', 'ese', 'eso', 'esos', 'esta', 'está', 'están', 'estas',
        'este', 'esto', 'estos', 'fue', 'fueron', 'ha', 'hace', 'hacia', 'han', 'hasta', 'hay', 'la',
        'las', 'le', 'les', 'lo', 'los', 'mas', 'más', 'me', 'mi', 'mis', 'mucho', 'muy', 'ni', 'no',
        'nos', 'nosotros', 'nuestra', 'nuestro', 'o', 'os', 'otra', 'otro', 'para', 'pero', 'poco',
        'por', 'porque', 'que', 'qué', 'quien', 'quién', 'se', 'ser', 'si', 'sí', 'sin', 'sobre',
        'son', 'su', 'sus', 'tambien', 'también', 'te', 'tiene', 'tienen', 'todo', 'todos', 'tu', 'tú',
        'tus', 'un', 'una', 'unas', 'uno', 'unos', 'vosotros', 'y', 'ya', 'yo',
        // Inglés
        'about', 'above', 'after', 'again', 'all', 'also', 'am', 'an', 'and', 'any', 'are', 'as', 'at',
        'be', 'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but', 'by', 'can',
        'did', 'do', 'does', 'doing', 'down', 'during', 'each', 'few', 'for', 'from', 'further', 'get',
        'had', 'has', 'have', 'having', 'he', 'her', 'here', 'hers', 'him', 'his', 'how', 'i', 'if',
        'in', 'into', 'is', 'it', 'its', 'just', 'like', 'me', 'more', 'most', 'my', 'new', 'no',
        'nor', 'not', 'now', 'of', 'off', 'on', 'once', 'only', 'or', 'other', 'our', 'ours', 'out',
        'over', 'own', 'same', 'she', 'should', 'so', 'some', 'such', 'than', 'that', 'the', 'their',
        'theirs', 'them', 'then', 'there', 'these', 'they', 'this', 'those', 'through', 'to', 'too',
        'under', 'until', 'up', 'very', 'was', 'we', 'were', 'what', 'when', 'where', 'which', 'while',
        'who', 'whom', 'why', 'will', 'with', 'would', 'you', 'your', 'yours',
    ];

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
                'type' => 'keywords',
                'data' => ['error' => 'html_not_found_in_cache'],
                'score' => 0,
            ]);
            return;
        }

        $text = $this->extractVisibleText($html);
        $words = $this->tokenize($text);
        $totalWords = count($words);

        $frequencies = array_count_values($words);
        arsort($frequencies);

        $topKeywords = [];
        foreach (array_slice($frequencies, 0, 10, true) as $word => $count) {
            $topKeywords[] = [
                'word' => $word,
                'count' => $count,
                'density' => $totalWords > 0 ? round(($count / $totalWords) * 100, 2) : 0,
            ];
        }

        $issues = [];
        foreach ($topKeywords as $keyword) {
            if ($keyword['density'] > self::KEYWORD_STUFFING_THRESHOLD) {
                $issues[] = 'keyword_stuffing';
                break;
            }
        }

        $this->audit->results()->create([
            'type' => 'keywords',
            'data' => [
                'total_words' => $totalWords,
                'top_keywords' => $topKeywords,
                'issues' => $issues,
            ],
            'score' => $this->calculateScore($issues, $totalWords),
        ]);
    }

    private function extractVisibleText(string $html): string
    {
        $crawler = new Crawler($html);

        $body = $crawler->filter('body');

        if ($body->count() === 0) {
            return '';
        }

        // Eliminamos del DOM los nodos que no aportan contenido "visible"
        // relevante para SEO antes de extraer el texto.
        $body->filter('script, style, noscript, nav, footer, svg, template')
            ->each(function (Crawler $node) {
                $domNode = $node->getNode(0);
                $domNode->parentNode?->removeChild($domNode);
            });

        return $body->text(normalizeWhitespace: true);
    }

    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');

        // Nos quedamos solo con letras (incluyendo acentos y ñ); el resto
        // (números, signos, emojis) actúa como separador.
        $rawWords = preg_split('/[^\p{L}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter($rawWords, function (string $word) {
            return mb_strlen($word) >= 3 && ! in_array($word, self::STOPWORDS, true);
        }));
    }

    private function calculateScore(array $issues, int $totalWords): int
    {
        $score = 100;

        if (in_array('keyword_stuffing', $issues, true)) {
            $score -= 40;
        }

        // Una página con muy poco texto es difícil de posicionar.
        if ($totalWords < 100) {
            $score -= 20;
        }

        return max(0, $score);
    }
}
