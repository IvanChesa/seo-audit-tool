<?php

namespace App\Analysis;

use DOMElement;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Thin wrapper around DomCrawler with the lookups the analyzers share.
 * Attribute values such as name="Description" are compared
 * case-insensitively, as browsers and search engines do.
 */
final class HtmlDocument
{
    private readonly Crawler $crawler;

    public function __construct(string $html)
    {
        $this->crawler = new Crawler;
        $this->crawler->addContent($html, 'text/html');
    }

    public function crawler(): Crawler
    {
        return $this->crawler;
    }

    /**
     * @return list<DOMElement>
     */
    public function elements(string $selector): array
    {
        $elements = [];

        foreach ($this->crawler->filter($selector) as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    /**
     * Document titles in order. <title> elements inside inline SVG icons are
     * accessible names for the graphic, not the page title, so they are ignored.
     *
     * @return list<string>
     */
    public function titles(): array
    {
        $titles = array_filter(
            $this->elements('title'),
            fn (DOMElement $title): bool => ! self::hasAncestor($title, 'svg'),
        );

        return array_values(array_map(
            fn (DOMElement $title): string => self::normalizeText($title->textContent),
            $titles,
        ));
    }

    public static function hasAncestor(DOMElement $element, string $localName): bool
    {
        for ($node = $element->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            if (strcasecmp($node->localName ?? '', $localName) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Values of <meta name="$name" content="..."> in document order.
     *
     * @return list<string>
     */
    public function metaContents(string $name, string $attribute = 'name'): array
    {
        $values = [];

        foreach ($this->elements("meta[{$attribute}]") as $meta) {
            if (strcasecmp(trim($meta->getAttribute($attribute)), $name) === 0 && $meta->hasAttribute('content')) {
                $values[] = self::normalizeText($meta->getAttribute('content'));
            }
        }

        return $values;
    }

    public function metaContent(string $name, string $attribute = 'name'): ?string
    {
        return $this->metaContents($name, $attribute)[0] ?? null;
    }

    /**
     * href of every <link> whose rel attribute contains $rel.
     *
     * @return list<string>
     */
    public function linkHrefs(string $rel): array
    {
        $hrefs = [];

        foreach ($this->elements('link[rel]') as $link) {
            $rels = preg_split('/\s+/', strtolower(trim($link->getAttribute('rel')))) ?: [];

            if (in_array(strtolower($rel), $rels, true)) {
                $hrefs[] = trim($link->getAttribute('href'));
            }
        }

        return $hrefs;
    }

    public function htmlAttribute(string $attribute): ?string
    {
        $html = $this->elements('html')[0] ?? null;

        if ($html === null || ! $html->hasAttribute($attribute)) {
            return null;
        }

        return trim($html->getAttribute($attribute));
    }

    public function baseHref(): ?string
    {
        $base = $this->elements('base[href]')[0] ?? null;

        return $base === null ? null : trim($base->getAttribute('href'));
    }

    public static function normalizeText(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
