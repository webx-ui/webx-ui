<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Demo;

use Illuminate\Support\Facades\DB;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\Localization\Locales;

/**
 * Four labels on the demo shop's products (§7 of the dictionaries spec): three badges for the
 * filter, and a service one that is neither — on products for a mailing, which is what a label
 * that only an editor sees is for.
 *
 * The products are the core demo's, taken from its part of the journal and counted by their order:
 * «Sale» goes where the core put an old price, so the badge and the crossed-out price agree. The
 * links go with the labels — the table deletes them when a label is.
 */
final class LabelsDemo
{
    /** code => [title, tone, badge, in the filter] */
    private const LABELS = [
        'top' => ['Top', 'primary', true, true],
        'sale' => ['Sale', 'danger', true, true],
        'new' => ['New', 'success', true, true],
        'newsletter' => ['Newsletter', 'neutral', false, false],
    ];

    public function __construct(
        private readonly Catalog $catalog,
        private readonly Locales $locales,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        $ids = $ledger->idsOf('catalog', Product::class);

        if ($ids === []) {
            $ledger->note('The catalogue demo is not seeded; the labels have no products to go on.');

            return;
        }

        if (Label::withTrashed()->exists()) {
            $ledger->note('The site already has labels; the demo left them alone.');

            return;
        }

        $locale = $this->locales->defaultCode();
        $labels = [];
        $position = 0;

        foreach (self::LABELS as $code => [$title, $tone, $badge, $filter]) {
            $label = new Label;
            $label->setTranslation('title', $locale, $title);
            $label->forceFill([
                'code' => $code,
                'color' => $tone,
                'is_badge' => $badge,
                'is_visible' => $filter,
                'position' => ++$position,
            ])->save();
            $ledger->created($label, 'Label '.$title);
            $labels[$code] = $label->getKey();
        }

        $rows = [];

        foreach (Product::withTrashed()->whereKey($ids)->orderBy('id')->get(['id', 'old_price'])->values() as $n => $product) {
            $on = array_filter([
                'top' => $n % 7 === 0,
                'sale' => $product->old_price !== null,
                'new' => $n % 9 === 1,
                'newsletter' => $n % 13 === 0,
            ]);

            foreach (array_keys($on) as $code) {
                $rows[] = ['label_id' => $labels[$code], 'product_id' => $product->getKey()];
            }
        }

        DB::table(Label::LINKS)->insert($rows);
        $this->catalog->touchQuery(Product::withTrashed()->whereKey($ids));
    }
}
