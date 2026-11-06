<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Doctor;

/**
 * Whether a robots.txt lets a crawler at a path, as Google reads one: the group that names the
 * crawler, or `*` when none does; the longest rule that matches wins, and `Allow` wins a tie.
 */
final class RobotsRules
{
    public static function allows(string $body, string $agent, string $path): bool
    {
        $groups = self::groups($body);
        $rules = $groups[strtolower($agent)] ?? $groups['*'] ?? [];
        $length = -1;
        $allowed = true;

        foreach ($rules as [$allow, $pattern]) {
            if ($pattern === '' || preg_match(self::regex($pattern), $path) !== 1) {
                continue;
            }

            if (strlen($pattern) > $length || (strlen($pattern) === $length && $allow)) {
                $length = strlen($pattern);
                $allowed = $allow;
            }
        }

        return $allowed;
    }

    /**
     * @return array<string, list<array{bool, string}>> the rules of each crawler named
     */
    private static function groups(string $body): array
    {
        $groups = [];
        $agents = [];
        $inRules = true;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $parts = explode(':', (string) preg_replace('/#.*$/', '', $line), 2);
            $field = strtolower(trim($parts[0]));
            $value = trim($parts[1] ?? '');

            if ($field === 'user-agent') {
                // Lines of agents in a row share the rules that follow them.
                $agents = $inRules ? [] : $agents;
                $agents[] = strtolower($value);
                $inRules = false;
            } elseif ($field === 'allow' || $field === 'disallow') {
                $inRules = true;

                foreach ($agents as $agent) {
                    $groups[$agent][] = [$field === 'allow', $value];
                }
            }
        }

        return $groups;
    }

    private static function regex(string $pattern): string
    {
        $anchored = str_ends_with($pattern, '$');
        $quoted = preg_quote($anchored ? substr($pattern, 0, -1) : $pattern, '~');

        return '~^'.str_replace('\*', '.*', $quoted).($anchored ? '$' : '').'~';
    }
}
