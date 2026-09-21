<?php

namespace Tests\Unit\Analysis;

use App\Analysis\Support\RobotsTxt;
use PHPUnit\Framework\TestCase;

class RobotsTxtTest extends TestCase
{
    public function test_an_empty_file_allows_everything(): void
    {
        $this->assertTrue(RobotsTxt::parse('')->isAllowed('/any/page'));
    }

    public function test_disallow_all_blocks_every_page(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow: /");

        $this->assertFalse($robots->isAllowed('/'));
        $this->assertFalse($robots->isAllowed('/blog/post'));
        $this->assertSame(['type' => 'disallow', 'path' => '/', 'agent' => '*'], $robots->decisiveRule('/blog/post'));
    }

    public function test_an_empty_disallow_allows_everything(): void
    {
        $this->assertTrue(RobotsTxt::parse("User-agent: *\nDisallow:")->isAllowed('/private'));
    }

    public function test_the_longest_matching_rule_wins_and_allow_wins_ties(): void
    {
        $robots = RobotsTxt::parse(implode("\n", [
            'User-agent: *',
            'Disallow: /shop/',
            'Allow: /shop/public/',
            'Disallow: /tie',
            'Allow: /tie',
        ]));

        $this->assertFalse($robots->isAllowed('/shop/cart'));
        $this->assertTrue($robots->isAllowed('/shop/public/offers'));
        $this->assertTrue($robots->isAllowed('/tie'));
    }

    public function test_wildcards_and_end_anchor(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow: /*.pdf$\nDisallow: /*?session=");

        $this->assertFalse($robots->isAllowed('/docs/manual.pdf'));
        $this->assertTrue($robots->isAllowed('/docs/manual.pdf.html'));
        $this->assertFalse($robots->isAllowed('/page?session=abc'));
        $this->assertTrue($robots->isAllowed('/page?lang=es'));
    }

    public function test_a_specific_group_replaces_the_wildcard_group(): void
    {
        $robots = RobotsTxt::parse(implode("\n", [
            'User-agent: *',
            'Disallow: /',
            '',
            'User-agent: Googlebot',
            'Disallow: /admin',
        ]));

        $this->assertTrue($robots->isAllowed('/blog', 'googlebot'));
        $this->assertFalse($robots->isAllowed('/admin', 'googlebot'));
        $this->assertFalse($robots->isAllowed('/blog', 'otherbot'));
    }

    public function test_consecutive_user_agents_share_rules_and_comments_are_ignored(): void
    {
        $robots = RobotsTxt::parse(implode("\n", [
            '# Comentario',
            'User-agent: bingbot',
            'User-agent: googlebot # también',
            'Disallow: /tmp/ # temporal',
        ]));

        $this->assertFalse($robots->isAllowed('/tmp/file', 'googlebot'));
        $this->assertFalse($robots->isAllowed('/tmp/file', 'bingbot'));
    }

    public function test_robots_txt_itself_is_always_allowed(): void
    {
        $this->assertTrue(RobotsTxt::parse("User-agent: *\nDisallow: /")->isAllowed('/robots.txt'));
    }

    public function test_it_collects_sitemaps_from_anywhere_in_the_file(): void
    {
        $robots = RobotsTxt::parse("Sitemap: https://example.com/sitemap.xml\nUser-agent: *\nDisallow:\nsitemap: https://example.com/news.xml\r\nSitemap: https://example.com/sitemap.xml");

        $this->assertSame(['https://example.com/sitemap.xml', 'https://example.com/news.xml'], $robots->sitemaps);
    }
}
