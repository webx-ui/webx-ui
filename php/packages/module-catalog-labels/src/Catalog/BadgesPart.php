<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Storefront\StorefrontPart;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\Localization\Locales;

/**
 * The badges on a card (`catalog.card.badges`): the labels drawn as badges, in the order of the
 * reference book, for the whole grid in one query. A service label is not a badge.
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

        $drawn = static function (Builder $query): void {
            $query->where('catalog_labels.is_badge', true);
        };

        foreach (Labels::on($products->modelKeys(), narrow: $drawn) as $id => $labels) {
            $badges[$id] = array_map(static fn (Label $label): array => [
                'code' => $label->code,
                'name' => $label->displayName($locale),
                'color' => $label->color,
            ], $labels);
        }

        return ['badges' => $badges];
    }
}
