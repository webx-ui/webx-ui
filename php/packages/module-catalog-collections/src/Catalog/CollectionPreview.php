<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Catalog;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Purchase\Purchasability;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Resolvers\CollectionResolvers;
use WebxUi\CatalogCollections\Resolvers\ManualResolver;

/**
 * What a collection would show, on values nobody saved (§6.3): the products in their order, how many
 * fit, and, of a hand-picked list, what will not reach the site and why, or a manager who added
 * five products and sees three has nothing to go on.
 *
 * Bounded like the site: a filter is asked for one more than the ceiling, with the limit in SQL, so
 * "more than that" is an answer and the catalogue is never counted row by row.
 */
final class CollectionPreview
{
    public const OVER_LIMIT = 'over-limit';

    public const GONE = 'gone';

    public const IN_BIN = 'in-bin';

    public const UNPUBLISHED = 'unpublished';

    public const HIDDEN = 'hidden';

    public const NO_LANGUAGE = 'no-language';

    public const STATUS = 'status';

    public function __construct(
        private readonly CollectionResolvers $resolvers,
        private readonly ManualResolver $manual,
        private readonly Purchasability $purchasability,
    ) {}

    /**
     * @param  list<int>  $picked  The hand-picked list, in its order.
     * @return array{products: list<array<string, mixed>>, count: int, more: bool, limit: int, dropped: list<array<string, mixed>>}
     */
    public function of(ProductCollection $collection, array $picked, string $locale): array
    {
        $max = ProductCollection::maxLimit();
        $limit = min(max((int) $collection->limit, 1), $max);
        $manual = $collection->source === ProductCollection::MANUAL;
        $picked = array_values(array_unique($picked));

        $ids = $manual
            ? $this->manual->pick($collection, $picked, max(count($picked), 1), $locale)
            : ($this->resolvers->find($collection->source)?->ids($collection, $max + 1, $locale) ?? []);

        $shown = array_slice($ids, 0, $limit);

        return [
            'products' => $this->rows($shown, $locale),
            'count' => min(count($ids), $manual ? count($ids) : $max),
            'more' => ! $manual && count($ids) > $max,
            'limit' => $limit,
            'dropped' => $manual ? $this->dropped($collection, $picked, $ids, $shown, $locale) : [],
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function rows(array $ids, string $locale): array
    {
        $products = $this->load($ids);
        $priced = (bool) config('webx-catalog.price.enabled', true);
        $rows = [];

        foreach ($ids as $id) {
            $product = $products->get($id);

            if (! $product instanceof Product) {
                continue;
            }

            $row = [...$this->named($product, $locale), 'image' => $product->mainImage()?->thumbUrl(160, 160)];

            if ($priced) {
                $row['price'] = $product->price === null ? null : (float) $product->price;
                $row['old_price'] = $product->old_price === null ? null : (float) $product->old_price;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  list<int>  $picked
     * @param  list<int>  $passed  What got past the rules.
     * @param  list<int>  $shown  What of that fits the limit.
     * @return list<array<string, mixed>>
     */
    private function dropped(ProductCollection $collection, array $picked, array $passed, array $shown, string $locale): array
    {
        $out = array_values(array_diff($picked, $shown));

        if ($out === []) {
            return [];
        }

        $products = $this->load($out);
        $visible = array_flip(Product::visibleAmong($out));
        $verdicts = $this->purchasability->forMany($products);
        $rows = [];

        foreach ($out as $id) {
            $product = $products->get($id);

            if (! $product instanceof Product) {
                $rows[] = ['id' => $id, 'name' => '#'.$id, 'sku' => null, 'reason' => self::GONE, 'status' => null];

                continue;
            }

            $reason = match (true) {
                in_array($id, $passed, true) => self::OVER_LIMIT,
                $product->trashed() => self::IN_BIN,
                ! $product->is_published => self::UNPUBLISHED,
                ! isset($visible[$id]) => self::HIDDEN,
                ! $product->hasUrlIn($locale) => self::NO_LANGUAGE,
                default => self::STATUS,
            };

            $rows[] = [
                ...$this->named($product, $locale),
                'reason' => $reason,
                'status' => $reason === self::STATUS ? ($verdicts[$id]->label ?? null) : null,
            ];
        }

        return $rows;
    }

    /**
     * @return array{id: int, name: string, sku: string|null}
     */
    private function named(Product $product, string $locale): array
    {
        return ['id' => (int) $product->id, 'name' => $product->displayName($locale), 'sku' => $product->sku];
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    private function load(array $ids): Collection
    {
        return Product::withTrashed()->with('images')->whereKey($ids)->get()->keyBy('id');
    }
}
