<?php

namespace App\Analysis\Support;

use App\Analysis\HtmlDocument;
use DOMElement;
use Symfony\Component\DomCrawler\UriResolver;

/**
 * Extracts the http(s) links of a page as absolute URLs. Fragments are
 * removed ("/page#section" is the same resource as "/page") and non-web
 * schemes (mailto:, tel:, javascript:...) are ignored.
 */
final class LinkExtractor
{
    /**
     * @return list<ExtractedLink>
     */
    public function extract(HtmlDocument $document, string $pageUrl): array
    {
        $base = $this->baseUrl($document, $pageUrl);
        $pageHost = UrlComparison::hostOf($pageUrl);
        $links = [];

        foreach ($document->elements('a[href]') as $anchor) {
            $url = $this->absoluteUrl(trim($anchor->getAttribute('href')), $base);

            if ($url === null) {
                continue;
            }

            $rel = preg_split('/\s+/', strtolower(trim($anchor->getAttribute('rel')))) ?: [];

            $links[] = new ExtractedLink(
                url: $url,
                text: $this->accessibleText($anchor),
                isInternal: UrlComparison::sameSite(UrlComparison::hostOf($url), $pageHost),
                isNofollow: in_array('nofollow', $rel, true),
            );
        }

        return $links;
    }

    private function baseUrl(HtmlDocument $document, string $pageUrl): string
    {
        $baseHref = $document->baseHref();

        if ($baseHref === null || $baseHref === '') {
            return $pageUrl;
        }

        $resolved = UriResolver::resolve($baseHref, $pageUrl);

        return preg_match('#^https?://#i', $resolved) === 1 ? $resolved : $pageUrl;
    }

    private function absoluteUrl(string $href, string $base): ?string
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        // Any explicit scheme other than http(s): mailto:, tel:, javascript:, data:...
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $href, $match) === 1 && ! in_array(strtolower($match[1]), ['http', 'https'], true)) {
            return null;
        }

        $absolute = (string) preg_replace('/#.*$/', '', UriResolver::resolve($href, $base));

        return preg_match('~^https?://[^/?#]+~i', $absolute) === 1 ? $absolute : null;
    }

    /**
     * Visible text, or the alt of a linked image, or aria-label / title.
     */
    private function accessibleText(DOMElement $anchor): string
    {
        $text = HtmlDocument::normalizeText($anchor->textContent);

        if ($text !== '') {
            return $text;
        }

        foreach ($anchor->getElementsByTagName('img') as $image) {
            $alt = HtmlDocument::normalizeText($image->getAttribute('alt'));

            if ($alt !== '') {
                return $alt;
            }
        }

        return HtmlDocument::normalizeText($anchor->getAttribute('aria-label') ?: $anchor->getAttribute('title'));
    }
}
