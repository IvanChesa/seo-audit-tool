<?php

namespace App\Analysis\Support;

/**
 * Splits visible text into words and counts the most frequent terms.
 * Stop words (articles, prepositions...) in Spanish and English are ignored
 * for the ranking but still count towards the total number of words, so the
 * density is the real share of the text.
 */
final class KeywordExtractor
{
    private const MIN_TERM_LENGTH = 3;

    private const STOPWORDS = [
        // Español
        'a', 'al', 'algo', 'ante', 'antes', 'aquel', 'aquella', 'aquellas', 'aquellos', 'aqui', 'aquí',
        'asi', 'así', 'como', 'cómo', 'con', 'contra', 'cual', 'cuál', 'cuando', 'cuándo', 'de', 'del',
        'desde', 'donde', 'dónde', 'durante', 'e', 'el', 'él', 'ella', 'ellas', 'ellos', 'en', 'entre',
        'era', 'eran', 'es', 'esa', 'esas', 'ese', 'eso', 'esos', 'esta', 'está', 'están', 'estas',
        'este', 'esto', 'estos', 'fue', 'fueron', 'ha', 'hace', 'hacia', 'han', 'hasta', 'hay', 'la',
        'las', 'le', 'les', 'lo', 'los', 'mas', 'más', 'me', 'mi', 'mis', 'mucho', 'muy', 'ni', 'no',
        'nos', 'nosotros', 'nuestra', 'nuestras', 'nuestro', 'nuestros', 'o', 'os', 'otra', 'otro', 'para', 'pero', 'poco',
        'por', 'porque', 'que', 'qué', 'quien', 'quién', 'se', 'ser', 'si', 'sí', 'sin', 'sobre',
        'son', 'su', 'sus', 'tambien', 'también', 'te', 'tiene', 'tienen', 'todo', 'todos', 'tu', 'tú',
        'tus', 'un', 'una', 'unas', 'uno', 'unos', 'vosotros', 'y', 'ya', 'yo',
        // English
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

    /**
     * @return list<string> Lower-cased words (letters only, numbers and symbols act as separators).
     */
    public function words(string $text): array
    {
        return preg_split('/[^\p{L}]+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param  list<string>  $words
     * @return list<array{term: string, count: int, density: float}>
     */
    public function topTerms(array $words, int $limit = 10): array
    {
        $total = count($words);

        if ($total === 0) {
            return [];
        }

        $stopwords = array_flip(self::STOPWORDS);
        $candidates = array_filter(
            $words,
            fn (string $word) => mb_strlen($word) >= self::MIN_TERM_LENGTH && ! isset($stopwords[$word]),
        );

        $frequencies = array_count_values($candidates);
        // Most frequent first; ties in alphabetical order so results are stable.
        uksort($frequencies, fn (string $a, string $b) => [$frequencies[$b], $a] <=> [$frequencies[$a], $b]);

        $terms = [];

        foreach (array_slice($frequencies, 0, $limit, true) as $term => $count) {
            $terms[] = [
                'term' => (string) $term,
                'count' => $count,
                'density' => round($count / $total * 100, 2),
            ];
        }

        return $terms;
    }
}
