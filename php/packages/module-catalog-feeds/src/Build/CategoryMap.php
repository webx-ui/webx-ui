<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

/**
 * The mapping of one feed, read up the tree (§6.2 of the feeds spec): a category's own value, or
 * the nearest ancestor's, so a whole branch is mapped by its top.
 *
 * @phpstan-type Mapped array{category: int, value: string, item: int|null, label: string|null}
 */
final class CategoryMap
{
    /** @var array<int, Mapped|null> */
    private array $found = [];

    /**
     * @param  array<int, Mapped>  $own  by category
     * @param  array<int, int|null>  $parents  category => parent
     */
    public function __construct(private readonly array $own, private readonly array $parents) {}

    /**
     * @return Mapped|null
     */
    public function find(int $category): ?array
    {
        if (array_key_exists($category, $this->found)) {
            return $this->found[$category];
        }

        $seen = [];
        $id = $category;

        while ($id !== null && ! isset($seen[$id])) {
            if (isset($this->own[$id])) {
                return $this->found[$category] = $this->own[$id];
            }

            $seen[$id] = true;
            $id = $this->parents[$id] ?? null;
        }

        return $this->found[$category] = null;
    }

    public function value(?int $category): string
    {
        return $category === null ? '' : ($this->find($category)['value'] ?? '');
    }
}
