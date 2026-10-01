<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange\Columns;

use Illuminate\Support\Collection;
use WebxUi\Catalog\Exchange\DescribesCell;
use WebxUi\Catalog\Exchange\ExchangeColumn;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Exchange\RowError;
use WebxUi\Catalog\Models\Product;
use WebxUi\Localization\Locales;

/**
 * A column that is one of the product's own columns, read and written as a value of one kind
 * (§7.1 of the exchange spec): text, a price, a whole number, yes or no, a unit.
 *
 * What a person types into a spreadsheet is accepted, not only what the export wrote: a price
 * with a comma or with spaces between thousands, `yes`, `TRUE`. What the export writes is what the
 * import reads back as the same value, so that a file sent back untouched changes nothing.
 */
final class ValueColumn implements DescribesCell, ExchangeColumn
{
    public const TEXT = 'text';

    public const DECIMAL = 'decimal';

    public const INTEGER = 'integer';

    public const BOOLEAN = 'boolean';

    public const UNIT = 'unit';

    private const YES = ['1', 'yes', 'true', 'y', '+'];

    private const NO = ['0', 'no', 'false', 'n', '-'];

    public function __construct(
        private readonly string $key,
        private readonly string $kind = self::TEXT,
        private readonly bool $localized = false,
        private readonly ?string $field = null,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return (string) __('webx-catalog::product.'.$this->field());
    }

    public function field(): string
    {
        return $this->field ?? $this->key;
    }

    public function localized(): bool
    {
        return $this->localized;
    }

    public function cellFormat(): string
    {
        return match ($this->kind) {
            self::DECIMAL => 'A number: a point or a comma, spaces between thousands allowed, no currency.',
            self::INTEGER => 'A whole number.',
            self::BOOLEAN => '1/0, yes/no, true/false.'.($this->key === 'is_published' ? ' Publishing without a main category is refused.' : ''),
            self::UNIT => 'A unit code from catalog://fields.',
            default => match ($this->key) {
                'slug' => 'Text; empty on a new product — made from the name.',
                'description' => 'HTML as it is.',
                'sku' => 'Text, unique among products, deleted ones included.',
                default => 'Text as it is.',
            },
        };
    }

    public function export(Collection $products, ?string $locale): array
    {
        $field = $this->field();
        $locale ??= app(Locales::class)->defaultCode();
        $cells = [];

        foreach ($products as $product) {
            /** @var Product $product */
            $value = $this->localized ? $product->getTranslation($field, $locale, false) : $product->getAttribute($field);

            $cells[(int) $product->id] = match (true) {
                $value === null => '',
                is_bool($value) => $value ? '1' : '0',
                is_scalar($value) => (string) $value,
                default => '',
            };
        }

        return $cells;
    }

    public function parse(string $cell, ImportContext $context): mixed
    {
        return match ($this->kind) {
            self::DECIMAL => self::decimal($cell),
            self::INTEGER => self::integer($cell),
            self::BOOLEAN => self::boolean($cell),
            self::UNIT => self::unit($cell),
            default => $cell,
        };
    }

    /**
     * `1 234,50`, `1,234.50`, `1234.5`: the last of `.` and `,` is the decimal point, the other
     * one and every kind of space separate thousands. Public for a satellite's number column.
     *
     * @throws RowError
     */
    public static function decimal(string $cell): float
    {
        $clean = (string) preg_replace('/[\s\x{00A0}\x{202F}\']+/u', '', $cell);
        $point = max((int) strrpos($clean, '.'), (int) strrpos($clean, ','));

        if (str_contains($clean, '.') || str_contains($clean, ',')) {
            $clean = str_replace(['.', ','], '', substr($clean, 0, $point)).'.'.substr($clean, $point + 1);
        }

        if (! is_numeric($clean)) {
            throw RowError::because('not-a-number');
        }

        return (float) $clean;
    }

    private static function integer(string $cell): int
    {
        $clean = trim($cell);

        if (preg_match('/^-?\d{1,9}$/', $clean) !== 1) {
            throw RowError::because('not-an-integer');
        }

        return (int) $clean;
    }

    /**
     * Yes or no as `is_published` reads it — the one way every yes/no column of a file does.
     *
     * @throws RowError
     */
    public static function boolean(string $cell): bool
    {
        $clean = mb_strtolower(trim($cell));

        return match (true) {
            in_array($clean, self::YES, true) => true,
            in_array($clean, self::NO, true) => false,
            default => throw RowError::because('not-a-boolean'),
        };
    }

    private static function unit(string $cell): string
    {
        $units = array_map('strval', (array) config('webx-catalog.units', []));
        $clean = trim($cell);

        if (! in_array($clean, $units, true)) {
            throw RowError::because('unknown-unit', ['known' => implode(', ', $units)]);
        }

        return $clean;
    }
}
