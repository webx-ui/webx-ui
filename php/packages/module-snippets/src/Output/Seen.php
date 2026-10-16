<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Output;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;

/**
 * The places a page of the site has printed (§10.3), the day each was last seen — what the
 * static search of the views cannot see: a place set by an expression, or printed from PHP.
 *
 * Written at most once a day per place: a page view must not be a cache write.
 */
final class Seen
{
    public const KEY = 'webx-snippets.seen';

    public function __construct(private readonly Cache $cache) {}

    public function mark(string $place): void
    {
        $today = Carbon::now()->toDateString();
        $seen = $this->all();

        if (($seen[$place] ?? null) === $today) {
            return;
        }

        $seen[$place] = $today;
        $this->cache->forever(self::KEY, $seen);
    }

    /** @return array<string, string> place → the day it was last printed */
    public function all(): array
    {
        $seen = $this->cache->get(self::KEY);

        return is_array($seen) ? array_filter($seen, is_string(...)) : [];
    }
}
