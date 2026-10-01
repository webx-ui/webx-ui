<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Exchange;

use Illuminate\Support\Collection;
use WebxUi\Catalog\Exchange\Columns\ValueColumn;
use WebxUi\Catalog\Exchange\DescribesCell;
use WebxUi\Catalog\Exchange\EntryColumn;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\CatalogProperties\Catalog\PropertiesPart;
use WebxUi\CatalogProperties\Models\Property;

/**
 * One property as a column of an exchange file (decision 12 of the properties spec, §7.2 of the
 * exchange spec): its code in the default language is the header — `-` written `_`, since a header
 * is `[a-z0-9_]` and a code never holds `_` — and it fills its own entry of `properties.values`.
 *
 * The cell is the value in its type's shape: a reference book by name (`;` between several, `/`
 * down a tree, `#id` where a name would mislead), a number without its unit, yes or no as
 * `is_published` reads it, and a text as it is — `code@de` for a translation.
 */
final class PropertyExchangeColumn implements DescribesCell, EntryColumn
{
    public function __construct(
        private readonly Property $property,
        private readonly ExportPage $page,
        private readonly string $locale,
    ) {}

    public static function keyOf(Property $property, string $locale): string
    {
        return str_replace('-', '_', $property->codeIn($locale));
    }

    public function key(): string
    {
        return self::keyOf($this->property, $this->locale);
    }

    public function label(): string
    {
        return $this->property->displayName($this->locale);
    }

    public function field(): string
    {
        return PropertiesPart::KEY.'.values';
    }

    public function entry(): string
    {
        return (string) $this->property->id;
    }

    public function localized(): bool
    {
        return $this->property->isText();
    }

    public function cellFormat(): string
    {
        $property = $this->property;

        return match ($property->type) {
            Property::SELECT => 'A value by its name in the default language, case aside, else its slug, or #id'
                .($property->is_tree ? '; a path of names through "/" down the tree' : '')
                .($property->is_multiple ? '; several through ";"' : '')
                .'. A missing value is created with create_missing.',
            Property::NUMBER => 'A number without its unit: a point or a comma.',
            Property::BOOL => '1/0, yes/no, true/false.',
            default => 'Text as it is; '.$this->key().'@<locale> for a translation.',
        }.' Only for products whose category has the property in its set.';
    }

    public function export(Collection $products, ?string $locale): array
    {
        $cells = [];

        foreach ($this->page->of($products, $this->property) as $id => [$inSet, $value]) {
            $cells[$id] = $inSet ? $this->cell($value, $locale ?? $this->locale) : '';
        }

        return $cells;
    }

    public function parse(string $cell, ImportContext $context): mixed
    {
        return match ($this->property->type) {
            Property::SELECT => $this->values($cell, $context),
            Property::NUMBER => ValueColumn::decimal($cell),
            Property::BOOL => ValueColumn::boolean($cell),
            default => $cell,
        };
    }

    private function cell(mixed $value, string $locale): string
    {
        return match ($this->property->type) {
            Property::SELECT => implode(';', array_map(
                fn (mixed $id): string => $this->page->paths($this->property)->path((int) $id),
                $value === null ? [] : (array) $value,
            )),
            Property::NUMBER => is_numeric($value) ? self::number((float) $value) : '',
            // «No» is no row at all; in the set it is still an answer, and the file says it.
            Property::BOOL => $value === true ? '1' : '0',
            default => is_array($value) && is_string($value[$locale] ?? null) ? $value[$locale] : '',
        };
    }

    /**
     * @return int|list<int>
     */
    private function values(string $cell, ImportContext $context): int|array
    {
        $paths = ValuePaths::of($this->property, $context);

        if (! $this->property->is_multiple) {
            return $paths->resolve($cell, $context);
        }

        $ids = [];

        foreach (explode(';', $cell) as $one) {
            if (trim($one) !== '') {
                $ids[] = $paths->resolve($one, $context);
            }
        }

        return array_values(array_unique($ids));
    }

    /** The number as stored, without the zeros its six places add: `1.35`, `12`. */
    private static function number(float $number): string
    {
        $text = rtrim(rtrim(sprintf('%.6F', $number), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }
}
