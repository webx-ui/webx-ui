<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use WebxUi\Catalog\Bulk\BulkAction;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartField;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogLabels\Models\Label;

/**
 * Put a label on every product chosen, or take it off (§3 of the dictionaries spec) — and taking
 * it off everywhere is the way to a label that may be deleted. The runner marks the chunk for the
 * engine; a product that already is as asked changes nothing and says nothing.
 */
final class LabelAction implements BulkAction
{
    public function __construct(private readonly bool $add) {}

    public function key(): string
    {
        return $this->add ? 'add-label' : 'remove-label';
    }

    public function label(): string
    {
        return (string) __('webx-catalog-labels::bulk.'.$this->key());
    }

    public function permission(): string
    {
        return 'catalog.manage';
    }

    public function trashed(): bool
    {
        return false;
    }

    public function params(): array
    {
        return [new PartField('label_id', 'id', 'webx-catalog-labels::bulk.label', ['required', 'integer'], 'catalog_labels_list', LabelsServiceProvider::SOURCE)];
    }

    public function rules(): array
    {
        return ['label_id' => ['required', 'integer', Rule::exists('catalog_labels', 'id')->whereNull('deleted_at')]];
    }

    public function apply(Product $product, array $params): array
    {
        $id = (int) $params['label_id'];
        $links = DB::table(Label::LINKS)->where('product_id', $product->id);
        $before = $links->clone()->orderBy('label_id')->pluck('label_id')->map(static fn (mixed $one): int => (int) $one)->all();

        if ($this->add === in_array($id, $before, true)) {
            return [];
        }

        $this->add
            ? DB::table(Label::LINKS)->insert(['label_id' => $id, 'product_id' => $product->id])
            : $links->clone()->where('label_id', $id)->delete();

        $after = $this->add ? [...$before, $id] : array_values(array_diff($before, [$id]));

        return [[
            'field' => LabelsPart::KEY.'.ids',
            'from' => Labels::names(array_values($before)),
            'to' => Labels::names($after),
            'label' => (string) __('webx-catalog-labels::product.labels'),
        ]];
    }
}
