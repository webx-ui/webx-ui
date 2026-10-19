<?php

declare(strict_types=1);

namespace WebxUi\Blog\Support;

use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Author;

/**
 * Which accounts have something on the site now — the question {@see Author}
 * asks for its visibility and its `robots`, by `user_id`.
 *
 * One author page asks it seven times: the handler, the SEO fallback, every `hreflang` line and
 * every switcher link, the site bar — and not always of the same instance. Remembered for the
 * request only: the answer moves with `published_at <= now`, so the next request asks again, and
 * a save of an article in this one forgets it (`Article::booted()`). Scoped, so a job keeps only
 * its own.
 */
final class PublishedAuthors
{
    /** @var array<int, bool> */
    private array $known = [];

    public function has(int $userId): bool
    {
        return $this->among([$userId])[$userId];
    }

    /**
     * The answer for each of these accounts, in one query for the ones not asked about yet.
     *
     * @param  list<int>  $userIds
     * @return array<int, bool>
     */
    public function among(array $userIds): array
    {
        $unknown = array_values(array_filter(array_unique($userIds), fn (int $id): bool => ! isset($this->known[$id])));

        if ($unknown !== []) {
            $found = Article::query()->published()->whereIn('author_id', $unknown)->distinct()->pluck('author_id')
                ->map(static fn (mixed $id): int => (int) $id)->all();

            foreach ($unknown as $id) {
                $this->known[$id] = in_array($id, $found, true);
            }
        }

        return array_intersect_key($this->known, array_flip($userIds));
    }

    /** What a list read already, `withExists`, so that nobody asks it again. */
    public function put(int $userId, bool $has): void
    {
        $this->known[$userId] = $has;
    }

    public function forget(): void
    {
        $this->known = [];
    }
}
