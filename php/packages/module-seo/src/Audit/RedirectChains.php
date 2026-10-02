<?php

declare(strict_types=1);

namespace WebxUi\Seo\Audit;

use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Panel\SeoRules;
use WebxUi\Seo\Panel\UrlMatcher;

/**
 * The redirects table walked the way the middleware would walk it, hop by hop, without a
 * request: where an address ends up, and which rows sent it there.
 *
 * What both `seo.redirect_chain` (the table, before any crawl) and the `seo.collapse-chain` fix
 * (a chain the crawl saw) stand on.
 */
final readonly class RedirectChains
{
    private const MAX = 10;

    public function __construct(
        private SeoRules $rules,
        private UrlMatcher $matcher,
    ) {}

    /**
     * Every hop from the address: the row that answered, where from, where to. Stops at an
     * address no row covers, at a loop, at another host, or after ten hops.
     *
     * @return array{hops: list<array{id: int, from: string, to: string, exact: bool}>, loop: bool}
     */
    public function walk(string $url): array
    {
        $rows = $this->rules->redirects();
        $current = UrlNormaliser::normalise($url);
        $seen = [$current => true];
        $hops = [];

        while (count($hops) < self::MAX) {
            $row = $this->matcher->match($current, $rows);

            if ($row === null) {
                break;
            }

            $target = UrlMatcher::target((string) ($row['match_type'] ?? ''), (string) ($row['pattern'] ?? ''), (string) ($row['target'] ?? ''), $current);

            if ($target === '' || UrlNormaliser::normalise($target) === $current) {
                break;
            }

            $hops[] = [
                'id' => (int) ($row['id'] ?? 0),
                'from' => $current,
                'to' => $target,
                'exact' => ($row['match_type'] ?? null) === UrlMatcher::EXACT,
            ];

            // Another host is the end of what this table can know.
            if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $target) === 1) {
                break;
            }

            $current = UrlNormaliser::normalise($target);

            if (isset($seen[$current])) {
                return ['hops' => $hops, 'loop' => true];
            }

            $seen[$current] = true;
        }

        return ['hops' => $hops, 'loop' => false];
    }

    /**
     * The exact rows of a chain that would point straight at its end once collapsed: row id =>
     * [pattern, target now, target after]. A mask is left alone — its target is a template for
     * a thousand addresses, not this one.
     *
     * @return array<int, array{string, string, string}>
     */
    public function collapsible(string $url): array
    {
        $walk = $this->walk($url);

        if ($walk['loop'] || count($walk['hops']) < 2) {
            return [];
        }

        $end = $walk['hops'][count($walk['hops']) - 1]['to'];
        $rows = [];

        foreach (array_slice($walk['hops'], 0, -1) as $hop) {
            if ($hop['exact'] && $hop['to'] !== $end) {
                $rows[$hop['id']] = [$hop['from'], $hop['to'], $end];
            }
        }

        return $rows;
    }

    /** Points each collapsible row at the end; how many changed. */
    public function collapse(string $url): int
    {
        $changed = 0;

        foreach ($this->collapsible($url) as $id => [, , $end]) {
            $row = SeoRedirect::query()->find($id);

            if ($row instanceof SeoRedirect) {
                $row->update(['target' => $end]);
                $changed++;
            }
        }

        return $changed;
    }
}
