<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebxUi\Catalog\Bulk\BulkAction;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartField;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\Localization\Locales;

/**
 * The bulk actions of the properties (§9.4 of the properties spec): set a value, take it away, and
 * clear what the products hold outside their set. One class, three keys — they share the lookup of
 * the property and the way a value is written, which is {@see ProductValues::put()}, the form's.
 *
 * Setting a property a product's category does not have is refused for that product — an error
 * beside its name, the others go on: a value written there would be kept and shown nowhere.
 */
final class PropertyActions implements BulkAction
{
    public const SET = 'set-property';

    public const REMOVE = 'remove-property';

    public const CLEAR = 'clear-outside-set';

    public function __construct(
        private readonly string $key,
        private readonly Properties $properties,
        private readonly PropertySets $sets,
        private readonly ProductValues $values,
        private readonly Locales $locales,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return (string) __('webx-catalog-properties::bulk.'.$this->key);
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
        if ($this->key === self::CLEAR) {
            return [];
        }

        return [
            new PartField('property_id', 'id', 'webx-catalog-properties::product.property', ['required', 'integer'], 'catalog_properties_list', CatalogPropertiesServiceProvider::SOURCE),
            new PartField('value', 'mixed', 'webx-catalog-properties::product.value', $this->key === self::SET ? ['required'] : ['nullable']),
        ];
    }

    public function rules(): array
    {
        if ($this->key === self::CLEAR) {
            return [];
        }

        return [
            'property_id' => ['required', 'integer', Rule::exists('catalog_properties', 'id')->whereNull('deleted_at')],
            'value' => $this->key === self::SET ? ['required'] : ['nullable'],
        ];
    }

    public function apply(Product $product, array $params): array
    {
        if ($this->key === self::CLEAR) {
            return $this->clear($product);
        }

        $property = $this->properties->find((int) ($params['property_id'] ?? 0));

        if ($property === null) {
            throw ValidationException::withMessages(['property_id' => [(string) __('webx-catalog-properties::errors.unknown-property')]]);
        }

        if ($this->key === self::REMOVE) {
            return $this->values->remove($product, $property, $params['value'] ?? null);
        }

        if (! in_array((int) $property->id, $this->sets->effective($product->category_id), true)) {
            throw ValidationException::withMessages(['property_id' => [(string) __('webx-catalog-properties::errors.outside-set')]]);
        }

        return $this->values->put($product, $property, $params['value'] ?? null, add: true);
    }

    /**
     * @return list<array{field: string, from: mixed, to: mixed, label: string}>
     */
    private function clear(Product $product): array
    {
        $set = $this->sets->effective($product->category_id);
        $stored = $this->values->read([(int) $product->id])[(int) $product->id] ?? [];
        $locale = $this->locales->current();
        $changes = [];

        foreach ($stored as $propertyId => $value) {
            if (in_array($propertyId, $set, true)) {
                continue;
            }

            $property = $this->properties->find($propertyId);

            if ($property === null) {
                continue;
            }

            $changes[] = [
                'field' => PropertiesPart::KEY.'.values.'.$propertyId,
                'from' => $this->values->format($property, $value, $locale),
                'to' => null,
                'label' => $property->displayName($locale),
            ];
        }

        $keep = $set === [] ? [0] : $set;
        $live = array_keys($this->properties->all());

        // A property in the bin keeps its values: a restore brings them back (§3.3).
        DB::table(ProductValues::TABLE)
            ->where('product_id', $product->id)
            ->whereNotIn('property_id', $keep)
            ->whereIn('property_id', $live === [] ? [0] : $live)
            ->delete();

        return $changes;
    }
}
