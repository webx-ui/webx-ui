<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Catalog;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartField;
use WebxUi\Catalog\Parts\PartSchema;
use WebxUi\Catalog\Parts\ProductPart;
use WebxUi\CatalogLabels\Models\Label;

/**
 * The labels of a product in its form: `labels.ids`, a multiple choice (§3 of the dictionaries
 * spec). The list is the value — labels have no order on a product of their own, the badges stand
 * in the order of the reference book.
 */
final class LabelsPart implements ProductPart
{
    public const KEY = 'labels';

    public function key(): string
    {
        return self::KEY;
    }

    public function describe(): PartSchema
    {
        return new PartSchema('webx-catalog-labels::module.title', 'catalog-labels', [
            new PartField('ids', 'ids', 'webx-catalog-labels::product.labels', ['array', 'exists:catalog_labels,id'], 'catalog_labels_list'),
        ]);
    }

    public function rules(): array
    {
        return ['ids' => ['nullable', 'array', static function (string $attribute, mixed $value, Closure $fail): void {
            $ids = Labels::ids($value);

            // Trashed ones included: a label in the bin is still on its products.
            if ($ids !== [] && Label::withTrashed()->whereKey($ids)->count() !== count($ids)) {
                $fail((string) __('webx-catalog-labels::errors.unknown'));
            }
        }]];
    }

    public function read(Collection $products): array
    {
        $values = [];

        foreach ($products as $product) {
            $values[(int) $product->id] = ['ids' => []];
        }

        foreach (Labels::on($products->modelKeys(), withTrashed: true) as $id => $labels) {
            $values[$id] = ['ids' => array_map(static fn (Label $label): int => $label->id, $labels)];
        }

        return $values;
    }

    public function write(Product $product, array $input): array
    {
        if (! array_key_exists('ids', $input)) {
            return [];
        }

        $before = $this->current($product);
        $after = Labels::ids($input['ids']);
        sort($after);

        if ($before === $after) {
            return [];
        }

        DB::table(Label::LINKS)->where('product_id', $product->id)->whereNotIn('label_id', $after)->delete();
        DB::table(Label::LINKS)->insertOrIgnore(array_map(
            static fn (int $id): array => ['label_id' => $id, 'product_id' => $product->id],
            array_values(array_diff($after, $before)),
        ));

        return [[
            'field' => self::KEY.'.ids',
            'from' => Labels::names($before),
            'to' => Labels::names($after),
            'label' => (string) __('webx-catalog-labels::product.labels'),
        ]];
    }

    /**
     * @return list<int>
     */
    private function current(Product $product): array
    {
        $ids = DB::table(Label::LINKS)->where('product_id', $product->id)->orderBy('label_id')->pluck('label_id')
            ->map(static fn (mixed $id): int => (int) $id)->all();

        return array_values($ids);
    }
}
