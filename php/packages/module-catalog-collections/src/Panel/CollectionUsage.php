<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Panel;

use Illuminate\Contracts\Container\Container;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Panel\Usage;
use WebxUi\Cache\CacheGroups;

/**
 * Where a collection stands (§6.4 of the collections spec): the blocks that name it, in pages and
 * regions, in what the site shows and in drafts. A shortcode in a text and a tag in a template are
 * not counted, so no places does not mean the collection is printed nowhere; each of those is a
 * walk of its own, and it would be added here.
 *
 * Without `webx-ui/module-blocks` there is no block to count, and that is not the same as none.
 *
 * @phpstan-type Place array{model: string, id: int|string, title: string|null, published: bool, live: bool, draft: bool, url: string|null, region: string|null}
 */
final class CollectionUsage
{
    /** The block that places a collection, and the field that keeps its key. */
    public const BLOCK = 'product-collection';

    public const FIELD = 'collection';

    public function __construct(private readonly Container $container) {}

    /** Whether there is a block to count at all. */
    public function available(): bool
    {
        return class_exists(Usage::class) && $this->container->bound(Usage::class);
    }

    /**
     * How many places hold each collection, by key; null without blocks. A key absent from the map
     * is on none. The list asks once for all its rows: this is one walk, not one per collection.
     *
     * @return array<string, int>|null
     */
    public function counts(): ?array
    {
        return $this->available() ? array_map(count(...), $this->all()) : null;
    }

    /**
     * The places of one collection.
     *
     * @return list<Place>
     */
    public function of(string $key): array
    {
        return $this->all()[$key] ?? [];
    }

    /**
     * Every place, by key, on the group of block usage: the walk reads every row of every entity
     * with content. By language, unlike the counts of the blocks: a region's name is translated.
     *
     * @return array<string, list<Place>>
     */
    private function all(): array
    {
        if (! $this->available()) {
            return [];
        }

        $usage = $this->container->make(Usage::class);
        $groups = $this->container->make(CacheGroups::class);
        $models = $usage->models();

        // As in `Usage::counts()`: a site or a test that names its entities after boot still has
        // their saves move the group, and the list in the key keeps the two apart.
        $groups->bumpOn(Usage::GROUP, Region::class, ...$models);

        return $groups->remember(
            'catalog-collections.usage.'.md5(implode(',', $models)),
            [Usage::GROUP],
            static fn (): array => $usage->valued(self::BLOCK, self::FIELD),
        );
    }
}
