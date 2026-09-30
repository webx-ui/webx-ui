<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\Localization\Locales;

/**
 * The values of the products (§2, §3.1 of the properties spec): one row per value, one column per
 * type, read for many products in one query and written for one product at a time.
 *
 * The shape of a value is its type's: an id or a list of ids for a reference book, a number, `true`
 * for «yes» — «no» is no row at all — and a map of languages for a text. The form, the bulk actions
 * and an agent all write through {@see put()}, so the rules and the line of the journal are one.
 */
final class ProductValues
{
    public const TABLE = 'catalog_product_property_values';

    public function __construct(
        private readonly Properties $properties,
        private readonly Locales $locales,
    ) {}

    /**
     * Every stored value of these products, whether or not the property is in the set.
     *
     * @param  list<int>  $products
     * @return array<int, array<int, mixed>> product → property → value in its type's shape
     */
    public function read(array $products): array
    {
        if ($products === []) {
            return [];
        }

        $all = $this->properties->all();
        $values = [];

        $rows = DB::table(self::TABLE.' as stored')
            ->leftJoin('catalog_property_values as book', 'book.id', '=', 'stored.value_id')
            ->whereIn('stored.product_id', $products)
            ->orderBy('stored.product_id')
            ->orderBy('book.lft')
            ->orderBy('stored.id')
            ->get(['stored.product_id', 'stored.property_id', 'stored.value_id', 'stored.number', 'stored.flag', 'stored.text']);

        foreach ($rows as $row) {
            $property = $all[(int) $row->property_id] ?? null;

            if ($property === null) {
                continue;
            }

            $product = (int) $row->product_id;
            $id = (int) $property->id;

            match ($property->type) {
                Property::SELECT => $property->is_multiple
                    ? $values[$product][$id][] = (int) $row->value_id
                    : $values[$product][$id] = (int) $row->value_id,
                Property::NUMBER => $values[$product][$id] = (float) $row->number,
                Property::BOOL => $values[$product][$id] = true,
                default => $values[$product][$id] = (array) json_decode((string) $row->text, true),
            };
        }

        return $values;
    }

    /**
     * Write one property of one product. Null takes it away; a reference book of several values
     * takes a list; a text lays the languages sent over those it has.
     *
     * @param  bool  $add  a value of a book of several is added to what is there rather than replacing it
     * @return list<array{field: string, from: mixed, to: mixed, label: string}> the line of the journal, or none
     *
     * @throws ValidationException under `properties.values.{id}`
     */
    public function put(Product $product, Property $property, mixed $value, bool $add = false): array
    {
        $before = $this->read([(int) $product->id])[(int) $product->id][(int) $property->id] ?? null;
        $after = $this->normalise($property, $value, $before, $add);

        if ($this->same($property, $before, $after)) {
            return [];
        }

        DB::table(self::TABLE)->where('product_id', $product->id)->where('property_id', $property->id)->delete();

        $rows = [];

        foreach ($this->rows($property, $after) as $row) {
            $rows[] = ['product_id' => $product->id, 'property_id' => $property->id, 'value_id' => null, 'number' => null, 'flag' => null, 'text' => null, ...$row];
        }

        if ($rows !== []) {
            DB::table(self::TABLE)->insert($rows);
        }

        $locale = $this->locales->current();

        return [[
            'field' => PropertiesPart::KEY.'.values.'.$property->id,
            'from' => $this->format($property, $before, $locale),
            'to' => $this->format($property, $after, $locale),
            'label' => $property->displayName($locale),
        ]];
    }

    /**
     * Take a value off, or the whole property when `$value` is null.
     *
     * @return list<array{field: string, from: mixed, to: mixed, label: string}>
     */
    public function remove(Product $product, Property $property, mixed $value = null): array
    {
        if ($value === null || ! $property->isSelect()) {
            return $this->put($product, $property, null);
        }

        $before = $this->read([(int) $product->id])[(int) $product->id][(int) $property->id] ?? null;
        $drop = $this->ids($property, $value);

        if (! $property->is_multiple) {
            // Only the value named is taken away; a product with another one keeps it.
            return in_array($before, $drop, true) ? $this->put($product, $property, null) : [];
        }

        return $this->put($product, $property, array_values(array_diff((array) $before, $drop)));
    }

    /**
     * The value as words — a line of the journal, a cell of a list: `Black, Grey`, `1.35 kg`, `Yes`.
     */
    public function format(Property $property, mixed $value, string $locale): ?string
    {
        if ($value === null || $value === [] || $value === false) {
            return null;
        }

        return match ($property->type) {
            Property::SELECT => implode(', ', PropertyValue::query()->whereKey((array) $value)->orderBy('lft')->get()
                ->map(static fn (PropertyValue $one): string => $one->displayName($locale))->all()),
            Property::NUMBER => $property->formatNumber((float) $value, $locale),
            Property::BOOL => (string) __('webx-catalog-properties::product.yes', [], $locale),
            default => $this->textIn((array) $value, $locale),
        };
    }

    /**
     * @param  array<array-key, mixed>  $text
     */
    private function textIn(array $text, string $locale): ?string
    {
        foreach ([$locale, ...array_keys($text)] as $one) {
            if (isset($text[$one]) && is_string($text[$one]) && $text[$one] !== '') {
                return $text[$one];
            }
        }

        return null;
    }

    /**
     * @throws ValidationException
     */
    private function normalise(Property $property, mixed $value, mixed $before, bool $add): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($property->type) {
            Property::SELECT => $this->select($property, $value, $before, $add),
            Property::NUMBER => is_numeric($value) ? (float) $value : $this->fail($property, 'webx-catalog-properties::errors.number'),
            Property::BOOL => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? true : null,
            default => $this->text($property, $value, $before),
        };
    }

    /**
     * @throws ValidationException
     */
    private function select(Property $property, mixed $value, mixed $before, bool $add): mixed
    {
        $ids = $this->ids($property, $value);

        if ($add && $property->is_multiple) {
            $ids = array_values(array_unique([...(array) ($before ?? []), ...$ids]));
        }

        if ($ids === []) {
            return null;
        }

        if (! $property->is_multiple && count($ids) > 1) {
            $this->fail($property, 'webx-catalog-properties::errors.one-value');
        }

        $found = PropertyValue::query()->where('property_id', $property->id)->whereKey($ids)->orderBy('lft')->get(['id', 'lft', 'rgt']);

        if ($found->count() !== count($ids)) {
            $this->fail($property, 'webx-catalog-properties::errors.unknown-value');
        }

        if ($property->leaves_only && $found->contains(static fn (PropertyValue $one): bool => $one->rgt - $one->lft > 1)) {
            $this->fail($property, 'webx-catalog-properties::errors.leaves-only');
        }

        $ordered = $found->map(static fn (PropertyValue $one): int => (int) $one->id)->all();

        return $property->is_multiple ? $ordered : $ordered[0];
    }

    /**
     * @return list<int>
     *
     * @throws ValidationException
     */
    private function ids(Property $property, mixed $value): array
    {
        $ids = [];

        foreach (is_array($value) ? $value : [$value] as $one) {
            if (is_int($one) || (is_string($one) && ctype_digit($one))) {
                $ids[] = (int) $one;

                continue;
            }

            // A slug in any language, the one an agent read off an address or a list (§10).
            $slug = is_string($one) ? strtolower(trim($one)) : '';
            $found = preg_match(Property::CODE, $slug) === 1
                ? PropertyValue::query()->where('property_id', $property->id)->whereTranslationLikeAny('slug', $slug)->value('id')
                : null;

            if ($found === null) {
                $this->fail($property, 'webx-catalog-properties::errors.unknown-slug', ['slug' => is_scalar($one) ? (string) $one : '?']);
            }

            $ids[] = (int) $found;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, string>|null
     *
     * @throws ValidationException
     */
    private function text(Property $property, mixed $value, mixed $before): ?array
    {
        if (is_string($value)) {
            $value = [$this->locales->current() => $value];
        }

        if (! is_array($value)) {
            $this->fail($property, 'webx-catalog-properties::errors.text');
        }

        $merged = [...(array) ($before ?? []), ...$value];
        $text = [];

        foreach ($merged as $locale => $words) {
            if (is_string($words) && trim($words) !== '') {
                $text[(string) $locale] = trim($words);
            }
        }

        return $text === [] ? null : $text;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(Property $property, mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return match ($property->type) {
            Property::SELECT => array_map(static fn (int $id): array => ['value_id' => $id], (array) $value),
            Property::NUMBER => [['number' => $value]],
            Property::BOOL => [['flag' => true]],
            default => [['text' => json_encode($value, JSON_UNESCAPED_UNICODE)]],
        };
    }

    private function same(Property $property, mixed $before, mixed $after): bool
    {
        if ($property->isNumber() && is_numeric($before) && is_numeric($after)) {
            return abs((float) $before - (float) $after) < 0.0000005;
        }

        if (is_array($before) && is_array($after) && $property->isText()) {
            ksort($before);
            ksort($after);
        }

        return $before === $after;
    }

    /**
     * @throws ValidationException
     */
    /**
     * @param  array<string, string>  $replace
     */
    private function fail(Property $property, string $message, array $replace = []): never
    {
        throw ValidationException::withMessages([
            PropertiesPart::KEY.'.values.'.$property->id => [(string) __($message, ['property' => $property->displayName(), ...$replace])],
        ]);
    }
}
