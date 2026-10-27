<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Catalog;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Storefront\StorefrontPart;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\Localization\Locales;

/**
 * The badges on a card (`catalog.card.badges`): the labels drawn as badges, in the order of the
 * reference book, for the whole grid in one query. A service label is not a badge — but it is
 * loaded with the rest: the query that finds the badges finds every label of the page, and a
 * template reads them through {@see Labels::of()} without one of its own. A label in a
 * colour of its own carries both colours (`own`), the text worked out by contrast if none was set.
 */
final class BadgesPart implements StorefrontPart
{
    public function point(): string
    {
        return 'catalog.card.badges';
    }

    public function view(): string
    {
        return 'webx-catalog-labels::badges';
    }

    public function prepare(Collection $products): array
    {
        $locale = app(Locales::class)->current();
        $badges = [];

        foreach (app(Labels::class)->load($products->modelKeys()) as $id => $labels) {
            $drawn = array_values(array_filter($labels, static fn (Label $label): bool => $label->is_badge));

            if ($drawn !== []) {
                $badges[$id] = array_map(static fn (Label $label): array => [
                    'code' => $label->code,
                    'name' => $label->displayName($locale),
                    'color' => $label->color,
                    'own' => $label->ownColors(),
                ], $drawn);
            }
        }

        return ['badges' => $badges];
    }
}
