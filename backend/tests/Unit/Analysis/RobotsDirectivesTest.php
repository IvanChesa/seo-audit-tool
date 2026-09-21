<?php

namespace Tests\Unit\Analysis;

use App\Analysis\Support\RobotsDirectives;
use PHPUnit\Framework\TestCase;

class RobotsDirectivesTest extends TestCase
{
    public function test_meta_tags_are_split_normalised_and_deduplicated(): void
    {
        $this->assertSame(['noindex', 'follow'], RobotsDirectives::fromMetaTags(['NoIndex, Follow', 'noindex']));
    }

    public function test_header_directives_scoped_to_other_bots_are_ignored(): void
    {
        $this->assertSame([], RobotsDirectives::fromHeader('otherbot: noindex'));
        $this->assertSame(['noindex'], RobotsDirectives::fromHeader('googlebot: noindex'));
        $this->assertSame(['nofollow'], RobotsDirectives::fromHeader('nofollow, otherbot: noindex'));
    }

    public function test_valued_directives_are_not_mistaken_for_bot_names(): void
    {
        $this->assertSame(['max-snippet: 50', 'noarchive'], RobotsDirectives::fromHeader('max-snippet: 50, noarchive'));
    }

    public function test_none_blocks_indexing_and_following(): void
    {
        $this->assertTrue(RobotsDirectives::blocksIndexing(['none']));
        $this->assertTrue(RobotsDirectives::blocksFollowing(['none']));
        $this->assertFalse(RobotsDirectives::blocksIndexing(['index', 'follow']));
    }
}
