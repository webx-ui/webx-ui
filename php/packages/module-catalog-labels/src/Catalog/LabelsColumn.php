<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Catalog;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Panel\ProductColumn;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\Localization\Locales;

/**
 * The labels in the panel's list of products: the badges an editor would see on the card, and the
 * service ones too — the list is where somebody checks what is on a product.
 */
final class LabelsColumn implements ProductColumn
{
    public function key(): string
    {
        return 'labels';
    }

    public function label(): string
    {
        return (string) __('webx-catalog-labels::product.labels');
    }

    public function values(Collection $products): array
    {
        $locale = app(Locales::class)->current();
        $values = [];

        foreach (Labels::on($products->modelKeys()) as $id => $labels) {
            $values[$id] = array_map(static fn (Label $label): array => [
                'id' => $label->id,
                'name' => $label->displayName($locale),
                'code' => $label->code,
                'color' => $label->color,
            ], $labels);
        }

        return $values;
    }

    public function sort(): ?string
    {
        return null;
    }
}
