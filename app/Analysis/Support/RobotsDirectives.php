<?php

namespace App\Analysis\Support;

/**
 * Parses indexing directives from <meta name="robots"> and the X-Robots-Tag
 * header (e.g. "noindex, nofollow" or "googlebot: noindex").
 */
final class RobotsDirectives
{
    /**
     * Crawlers whose scoped directives apply to this audit. Directives scoped
     * to other bots ("otherbot: noindex") are ignored.
     */
    private const RELEVANT_AGENTS = ['googlebot', 'bingbot'];

    /**
     * Directives that take a value after a colon and are not bot names.
     */
    private const VALUED_DIRECTIVES = ['unavailable_after', 'max-snippet', 'max-image-preview', 'max-video-preview'];

    /**
     * @param  list<string>  $contents
     * @return list<string>
     */
    public static function fromMetaTags(array $contents): array
    {
        $directives = [];

        foreach ($contents as $content) {
            foreach (explode(',', strtolower($content)) as $directive) {
                if (($directive = trim($directive)) !== '') {
                    $directives[] = $directive;
                }
            }
        }

        return array_values(array_unique($directives));
    }

    /**
     * @return list<string>
     */
    public static function fromHeader(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            return [];
        }

        $directives = [];
        $applies = true;

        foreach (explode(',', strtolower($header)) as $token) {
            $token = trim($token);

            if (preg_match('/^([a-z0-9_-]+)\s*:\s*(.*)$/', $token, $match) === 1 && ! in_array($match[1], self::VALUED_DIRECTIVES, true)) {
                // "googlebot: noindex" scopes this and the following directives to one bot.
                $applies = in_array($match[1], self::RELEVANT_AGENTS, true);
                $token = trim($match[2]);
            }

            if ($applies && $token !== '') {
                $directives[] = $token;
            }
        }

        return array_values(array_unique($directives));
    }

    /**
     * @param  list<string>  $directives
     */
    public static function blocksIndexing(array $directives): bool
    {
        return in_array('noindex', $directives, true) || in_array('none', $directives, true);
    }

    /**
     * @param  list<string>  $directives
     */
    public static function blocksFollowing(array $directives): bool
    {
        return in_array('nofollow', $directives, true) || in_array('none', $directives, true);
    }
}
