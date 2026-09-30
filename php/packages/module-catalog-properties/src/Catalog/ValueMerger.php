<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\History\Journal;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Filter\FilterAliases;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogProperties\Models\PropertyValue;

/**
 * Merging a value into another of the same property — «Черный» into «Чёрный» (§3.3 of the
 * properties spec), what an editor does instead of deleting a value products hold.
 *
 * In one transaction: the products of `A` get `B` (a product of several values that had both keeps
 * one), the children of `A` move under `B`, every old address of `A` leads to `B`, `A` goes, and the
 * journal gets one row at `B`. In a tree `B` may not stand under `A` — its own parent would be
 * leaving.
 */
final class ValueMerger
{
    public function __construct(
        private readonly FilterAliases $aliases,
        private readonly Journal $journal,
        private readonly Catalog $catalog,
    ) {}

    /**
     * @return int how many products moved to `$into`
     *
     * @throws ValidationException
     */
    public function merge(PropertyValue $from, PropertyValue $into, bool $dryRun = false): int
    {
        $from = PropertyValue::query()->findOrFail($from->getKey());
        $into = PropertyValue::query()->findOrFail($into->getKey());

        if ($from->property_id !== $into->property_id || $from->is($into)) {
            throw ValidationException::withMessages(['into' => [(string) __('webx-catalog-properties::errors.merge-other')]]);
        }

        if ($into->lft > $from->lft && $into->rgt < $from->rgt) {
            throw ValidationException::withMessages(['into' => [(string) __('webx-catalog-properties::errors.merge-descendant')]]);
        }

        $products = DB::table(ProductValues::TABLE)->where('value_id', $from->id)->distinct()->pluck('product_id')
            ->map(static fn (mixed $id): int => (int) $id)->all();

        if ($dryRun) {
            return count($products);
        }

        DB::transaction(function () use ($from, $into, $products): void {
            // A product that holds both keeps one row of `B`.
            DB::table(ProductValues::TABLE)
                ->where('value_id', $from->id)
                ->whereIn('product_id', DB::table(ProductValues::TABLE)->select('product_id')->where('value_id', $into->id))
                ->delete();

            DB::table(ProductValues::TABLE)->where('value_id', $from->id)->update(['value_id' => $into->id]);

            foreach (PropertyValue::query()->where('parent_id', $from->id)->orderBy('lft')->pluck('id') as $child) {
                PropertyValue::query()->findOrFail($child)->appendTo(PropertyValue::query()->findOrFail($into->id));
            }

            $key = 'p.'.$from->property_id;
            // Before the delete: it would otherwise send what led to `A` nowhere.
            $this->aliases->retarget($key, FilterAliases::VALUE, (string) $from->id, (string) $into->id);

            $gone = PropertyValue::query()->findOrFail($from->id);
            $gone->quietly = true;
            $gone->delete();

            foreach ($from->getTranslations('slug') as $locale => $slug) {
                if (is_string($slug) && $slug !== '') {
                    $this->aliases->record($key, (string) $locale, FilterAliases::VALUE, $slug, (string) $into->id);
                }
            }

            $this->journal->record($into, HistoryEntry::UPDATED, [[
                'field' => 'merged',
                'label' => (string) __('webx-catalog-properties::value.merged'),
                'from' => null,
                'to' => (string) __('webx-catalog-properties::value.merged-line', [
                    'value' => $from->displayName(),
                    'count' => count($products),
                ]),
            ]]);

            if ($products !== []) {
                $this->catalog->touchQuery(Product::withTrashed()->whereKey($products));
            }
        });

        return count($products);
    }
}
