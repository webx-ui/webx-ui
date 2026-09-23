<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests\Fixtures;

use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;

/**
 * {@see Thing} again, under the alias `module-pages` registers.
 *
 * Only the demo needs this one: it is written to make a header out of pages, and the alias is
 * the one thing about pages it is allowed to know. A fixture rather than the real package,
 * because installing a tree, an address registry and a block constructor to prove that three
 * items land in the right menu is a great deal of machinery for three items.
 */
final class PageLinkSource implements LinkSource
{
    public function type(): string
    {
        return 'page';
    }

    public function model(): string
    {
        return Thing::class;
    }

    public function title(): string
    {
        return 'Pages';
    }

    public function icon(): string
    {
        return 'file';
    }

    public function order(): int
    {
        return 50;
    }

    public function permission(): ?string
    {
        return null;
    }

    /**
     * @return list<LinkCandidate>
     */
    public function search(string $query, string $locale, int $limit): array
    {
        return array_values($this->resolve(
            Thing::query()->orderBy('id')->limit($limit)->pluck('id')->map(intval(...))->all(),
            $locale,
        ));
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, LinkCandidate>
     */
    public function resolve(array $ids, string $locale): array
    {
        $candidates = [];

        foreach (Thing::query()->whereKey($ids)->get() as $thing) {
            $title = $thing->getTranslation('title', $locale, false);

            $candidates[(int) $thing->getKey()] = new LinkCandidate(
                id: (int) $thing->getKey(),
                title: is_string($title) ? $title : '',
                url: '/'.ltrim($thing->slug, '/'),
                available: $thing->published,
            );
        }

        return $candidates;
    }
}
