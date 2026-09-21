<?php

namespace App\Analysis\Support;

/**
 * Minimal robots.txt parser following RFC 9309:
 *  - rules are grouped by the User-agent lines that precede them;
 *  - a crawler uses the groups naming it, or the "*" groups otherwise;
 *  - the longest matching rule wins and Allow wins ties;
 *  - "*" matches any sequence of characters and a trailing "$" anchors the end.
 */
final class RobotsTxt
{
    /**
     * @param  list<array{agents: list<string>, rules: list<array{type: string, path: string}>}>  $groups
     * @param  list<string>  $sitemaps
     */
    private function __construct(
        private readonly array $groups,
        public readonly array $sitemaps,
    ) {}

    public static function parse(string $content): self
    {
        $groups = [];
        $sitemaps = [];
        $agents = [];
        $rules = [];
        $previousWasAgent = false;

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));

            if (! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'sitemap') {
                if ($value !== '') {
                    $sitemaps[] = $value;
                }

                continue;
            }

            if ($field === 'user-agent') {
                // A User-agent line after rules starts a new group; consecutive
                // User-agent lines share the same rules.
                if (! $previousWasAgent && $agents !== []) {
                    $groups[] = ['agents' => $agents, 'rules' => $rules];
                    $agents = [];
                    $rules = [];
                }

                $agents[] = strtolower(explode('/', $value)[0]);
                $previousWasAgent = true;

                continue;
            }

            if (($field === 'allow' || $field === 'disallow') && $agents !== []) {
                $rules[] = ['type' => $field, 'path' => $value];
            }

            $previousWasAgent = false;
        }

        if ($agents !== []) {
            $groups[] = ['agents' => $agents, 'rules' => $rules];
        }

        return new self($groups, array_values(array_unique($sitemaps)));
    }

    /**
     * Returns the rule that decides whether $pathAndQuery may be crawled, or
     * null when no rule applies (everything is allowed by default).
     *
     * @return array{type: string, path: string, agent: string}|null
     */
    public function decisiveRule(string $pathAndQuery, string $agent = 'googlebot'): ?array
    {
        if ($pathAndQuery === '/robots.txt') {
            return null;
        }

        [$rules, $groupAgent] = $this->rulesFor(strtolower($agent));
        $best = null;

        foreach ($rules as $rule) {
            // "Disallow:" with an empty value allows everything: it never matches.
            if ($rule['path'] === '' || ! $this->matches($rule['path'], $pathAndQuery)) {
                continue;
            }

            $isLonger = $best === null || strlen($rule['path']) > strlen($best['path']);
            $wins = $isLonger || (strlen($rule['path']) === strlen($best['path']) && $rule['type'] === 'allow');

            if ($wins) {
                $best = $rule;
            }
        }

        return $best === null ? null : [...$best, 'agent' => $groupAgent];
    }

    public function isAllowed(string $pathAndQuery, string $agent = 'googlebot'): bool
    {
        return ($this->decisiveRule($pathAndQuery, $agent)['type'] ?? 'allow') === 'allow';
    }

    /**
     * @return array{0: list<array{type: string, path: string}>, 1: string}
     */
    private function rulesFor(string $agent): array
    {
        foreach ([$agent, '*'] as $candidate) {
            $rules = [];

            foreach ($this->groups as $group) {
                if (in_array($candidate, $group['agents'], true)) {
                    $rules = [...$rules, ...$group['rules']];
                }
            }

            if ($rules !== [] || $this->hasGroupFor($candidate)) {
                return [$rules, $candidate];
            }
        }

        return [[], '*'];
    }

    private function hasGroupFor(string $agent): bool
    {
        foreach ($this->groups as $group) {
            if (in_array($agent, $group['agents'], true)) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $pattern = $anchored ? substr($pattern, 0, -1) : $pattern;
        $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).($anchored ? '$' : '').'#';

        return preg_match($regex, $path) === 1;
    }
}
