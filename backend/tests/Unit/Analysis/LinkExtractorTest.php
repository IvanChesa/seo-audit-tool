<?php

namespace Tests\Unit\Analysis;

use App\Analysis\HtmlDocument;
use App\Analysis\Support\ExtractedLink;
use App\Analysis\Support\LinkExtractor;
use PHPUnit\Framework\TestCase;

class LinkExtractorTest extends TestCase
{
    /**
     * @return list<ExtractedLink>
     */
    private function extract(string $body, string $pageUrl = 'https://www.example.com/blog/post'): array
    {
        return (new LinkExtractor)->extract(new HtmlDocument("<html><body>{$body}</body></html>"), $pageUrl);
    }

    public function test_links_are_resolved_classified_and_stripped_of_fragments(): void
    {
        $links = $this->extract(implode('', [
            '<a href="/contacto#form">Contacto</a>',
            '<a href="otra">Relativa</a>',
            '<a href="https://example.com/sin-www">Mismo sitio sin www</a>',
            '<a href="//cdn.other.org/lib" rel="nofollow noopener">CDN</a>',
        ]));

        $this->assertSame([
            'https://www.example.com/contacto',
            'https://www.example.com/blog/otra',
            'https://example.com/sin-www',
            'https://cdn.other.org/lib',
        ], array_map(fn (ExtractedLink $link) => $link->url, $links));
        $this->assertSame([true, true, true, false], array_map(fn (ExtractedLink $link) => $link->isInternal, $links));
        $this->assertTrue($links[3]->isNofollow);
    }

    public function test_non_web_links_and_same_page_anchors_are_ignored(): void
    {
        $links = $this->extract('<a href="mailto:a@b.c">m</a><a href="tel:+34600">t</a><a href="javascript:void(0)">j</a><a href="#top">a</a><a href="">e</a><a>no href</a>');

        $this->assertSame([], $links);
    }

    public function test_base_href_is_respected(): void
    {
        $html = '<html><head><base href="https://example.com/docs/"></head><body><a href="guia">Guía</a></body></html>';

        $links = (new LinkExtractor)->extract(new HtmlDocument($html), 'https://example.com/');

        $this->assertSame('https://example.com/docs/guia', $links[0]->url);
    }

    public function test_accessible_text_falls_back_to_image_alt_and_aria_label(): void
    {
        $links = $this->extract('<a href="/a"><img src="x.png" alt="Logo"></a><a href="/b" aria-label="Cerrar"><svg></svg></a><a href="/c"><img src="y.png"></a>');

        $this->assertSame(['Logo', 'Cerrar', ''], array_map(fn (ExtractedLink $link) => $link->text, $links));
    }
}
