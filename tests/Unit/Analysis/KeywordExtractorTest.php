<?php

namespace Tests\Unit\Analysis;

use App\Analysis\Support\KeywordExtractor;
use PHPUnit\Framework\TestCase;

class KeywordExtractorTest extends TestCase
{
    public function test_words_are_lower_cased_letters_only_including_accents(): void
    {
        $words = (new KeywordExtractor)->words('¡Hola, CAMIÓN número 42! Ñandú—ok');

        $this->assertSame(['hola', 'camión', 'número', 'ñandú', 'ok'], $words);
    }

    public function test_density_is_computed_over_every_word_including_stopwords(): void
    {
        $extractor = new KeywordExtractor;
        $words = $extractor->words('el jardín y el jardín de la casa con plantas');

        $terms = $extractor->topTerms($words);

        // 10 words in total; "jardín" appears twice → 20 %. Stop words are not ranked.
        $this->assertSame(['term' => 'jardín', 'count' => 2, 'density' => 20.0], $terms[0]);
        $this->assertNotContains('el', array_column($terms, 'term'));
        $this->assertNotContains('con', array_column($terms, 'term'));
    }

    public function test_ties_are_sorted_alphabetically_for_stable_results(): void
    {
        $extractor = new KeywordExtractor;

        $terms = $extractor->topTerms($extractor->words('zeta alfa beta zeta alfa beta'));

        $this->assertSame(['alfa', 'beta', 'zeta'], array_column($terms, 'term'));
    }

    public function test_short_words_are_not_ranked_and_empty_text_returns_nothing(): void
    {
        $extractor = new KeywordExtractor;

        $this->assertSame([], $extractor->topTerms($extractor->words('ok ok ok ok')));
        $this->assertSame([], $extractor->topTerms([]));
    }
}
