<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Catalog;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartField;
use WebxUi\Catalog\Parts\PartSchema;
use WebxUi\Catalog\Parts\ProductPart;

/**
 * The properties in the product form (§7.3 of the properties spec, «Часть формы товара»): one
 * field, `properties.values`, an object `{ "<property_id>": value }` in each type's shape.
 *
 * A key left out is not touched, and `null` takes the value away. A property outside the set of
 * the product's main category is a 422 under its own field — checked in {@see write()}, inside the
 * form's transaction, because the same save may have just changed the main category: the set that
 * counts is the one the product will have, not the one it had. A refusal there takes the whole save
 * back, the product's own fields with it.
 *
 * Reading gives the values of the set in force, and apart from them `properties.outside` — what the
 * product holds of properties its category no longer has (decision 6), for the form to show read
 * only. Sent back, it is ignored: it is not a field of the screen.
 */
final class PropertiesPart implements ProductPart
{
    public const KEY = 'properties';

    public function __construct(
        private readonly Properties $properties,
        private readonly PropertySets $sets,
        private readonly ProductValues $values,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function describe(): PartSchema
    {
        return new PartSchema('webx-catalog-properties::product.properties', 'catalog-properties', [
            new PartField(
                'values',
                'object',
                'webx-catalog-properties::product.properties',
                [
                    'nullable',
                    'array',
                    '{ "<property_id>": value | [values] | number | true | { "<locale>": text } | null }',
                    'a value of a reference book is its id or its slug in any language (catalog_property_values_list); an unknown slug is refused — make it with catalog_property_values_create',
                    'only the properties of the set of the main category (catalog_categories_properties); a key left out is not touched, null takes the value away',
                ],
                'catalog://properties',
            ),
        ]);
    }

    public function rules(): array
    {
        return ['values' => ['nullable', 'array']];
    }

    public function read(Collection $products): array
    {
        $stored = $this->values->read(array_map('intval', $products->modelKeys()));
        $read = [];

        foreach ($products as $product) {
            $id = (int) $product->id;
            $held = $stored[$id] ?? [];
            $values = [];

            // In the order of the set, which is the order of the form.
            foreach ($this->sets->effective($product->category_id) as $property) {
                if (array_key_exists($property, $held)) {
                    $values[$property] = $held[$property];
                    unset($held[$property]);
                }
            }

            $outside = $held;

            $read[$id] = ['values' => $values, 'outside' => $outside];
        }

        return $read;
    }

    public function write(Product $product, array $input): array
    {
        if (! is_array($input['values'] ?? null)) {
            return [];
        }

        $set = array_flip($this->sets->effective($product->category_id));
        $changes = [];
        $errors = [];

        foreach ($input['values'] as $key => $value) {
            $property = is_numeric($key) ? $this->properties->find((int) $key) : null;

            // Nothing asked of a property outside the set: there is nothing of it in the form to take
            // away — a file with a column per property sends that for every product of another
            // category, and what such a product holds stays (decision 6).
            if ($property !== null && $value === null && ! isset($set[(int) $property->id])) {
                continue;
            }

            if ($property === null || ! isset($set[(int) $property->id])) {
                $errors[self::KEY.'.values.'.$key] = [(string) __('webx-catalog-properties::errors.outside-set')];

                continue;
            }

            try {
                $changes = [...$changes, ...$this->values->put($product, $property, $value)];
            } catch (ValidationException $refused) {
                $errors = [...$errors, ...$refused->errors()];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $changes;
    }
}
