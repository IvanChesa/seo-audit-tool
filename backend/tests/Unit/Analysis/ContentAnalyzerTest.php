<?php

namespace Tests\Unit\Analysis;

use App\Analysis\Analyzers\ContentAnalyzer;
use App\Analysis\SectionResult;
use App\Analysis\Support\KeywordExtractor;
use Tests\TestCase;

class ContentAnalyzerTest extends TestCase
{
    private function analyze(string $html): SectionResult
    {
        return (new ContentAnalyzer(new KeywordExtractor))->analyze($this->context($html));
    }

    /**
     * @return list<string>
     */
    private function issueCodes(SectionResult $result): array
    {
        return array_map(fn ($issue) => $issue->code, $result->issues);
    }

    /**
     * Unique letter-only words ("vozaa vozab ..."): digits would split them.
     */
    private function distinctWords(int $count): string
    {
        return implode(' ', array_map(
            fn (int $i) => 'voz'.chr(97 + intdiv($i, 26) % 26).chr(97 + $i % 26),
            range(0, $count - 1),
        ));
    }

    public function test_a_complete_article_has_no_issues(): void
    {
        $result = $this->analyze($this->fixture('good-page.html'));

        $this->assertSame([], $this->issueCodes($result));
        $this->assertGreaterThan(ContentAnalyzer::THIN_CONTENT_WORDS, $result->data['word_count']);
        $this->assertNotEmpty($result->data['top_terms']);
        $this->assertSame(['total' => 2, 'missing_alt' => 0, 'decorative' => 1, 'missing_alt_examples' => []], $result->data['images']);
    }

    public function test_navigation_footer_and_scripts_do_not_count_as_content(): void
    {
        $result = $this->analyze('<body><nav>uno dos tres</nav><script>var cuatro = 1;</script><p>cinco seis</p><footer>siete</footer></body>');

        $this->assertSame(2, $result->data['word_count']);
    }

    public function test_very_little_text_is_a_medium_issue_and_short_text_a_low_one(): void
    {
        $veryThin = $this->analyze('<body><p>'.str_repeat('palabra ', 50).'</p></body>');
        $short = $this->analyze('<body><p>'.$this->distinctWords(200).'</p></body>');

        $this->assertSame('medium', $veryThin->issues[0]->severity->value);
        $this->assertSame('low', $short->issues[0]->severity->value);
        $this->assertSame(['low_word_count'], $this->issueCodes($short));
    }

    public function test_repetition_is_only_flagged_in_texts_long_enough(): void
    {
        $stuffed = $this->distinctWords(230).' '.str_repeat('zapatillas ', 30);

        $long = $this->analyze("<body><p>{$stuffed}</p></body>");
        $shortRepetitive = $this->analyze('<body><p>'.str_repeat('zapatillas baratas ', 20).'</p></body>');

        $this->assertContains('possible_keyword_stuffing', $this->issueCodes($long));
        $this->assertStringContainsString('«zapatillas»', (string) $long->issues[array_search('possible_keyword_stuffing', $this->issueCodes($long), true)]->evidence);
        // 40 words: the percentage is not meaningful, so it is not reported as stuffing.
        $this->assertNotContains('possible_keyword_stuffing', $this->issueCodes($shortRepetitive));
    }

    public function test_images_without_alt_are_listed_while_decorative_and_hidden_ones_are_not(): void
    {
        $result = $this->analyze($this->fixture('bad-page.html'));

        $this->assertContains('images_missing_alt', $this->issueCodes($result));
        $this->assertSame(3, $result->data['images']['total']);
        $this->assertSame(2, $result->data['images']['missing_alt']);
        $this->assertSame(['/banner.jpg', '/logo.png'], $result->data['images']['missing_alt_examples']);
    }

    public function test_a_page_without_body_or_images(): void
    {
        $result = $this->analyze('');

        $this->assertSame(0, $result->data['word_count']);
        $this->assertSame([], $result->data['top_terms']);
        $this->assertSame(0, $result->data['images']['total']);
    }
}
