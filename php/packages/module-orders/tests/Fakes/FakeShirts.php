<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Fakes;

use WebxUi\Cart\ChoiceOption;
use WebxUi\Cart\Contracts\ChecksOptions;
use WebxUi\Cart\Contracts\FindsPurchasables;
use WebxUi\Cart\Contracts\Purchasable;
use WebxUi\Cart\Contracts\ResolvesWithOptions;
use WebxUi\Cart\PurchasableChoice;
use WebxUi\Cart\PurchasableLine;

/**
 * A product with a variant to pick, found by its name: shirt 1 in S, M or L at 20.00, the size in
 * the line's label. «Bought as a buy button sends it» — without a size the line is refused.
 */
final class FakeShirts implements ChecksOptions, FindsPurchasables, Purchasable, ResolvesWithOptions
{
    public const array SIZES = ['s' => 'S', 'm' => 'M', 'l' => 'L'];

    public function resolve(array $refs, string $locale): array
    {
        return [];
    }

    public function resolveLines(array $lines, string $locale): array
    {
        $found = [];

        foreach ($lines as $line) {
            if ($line->ref === 1) {
                $size = self::SIZES[$line->options['size'] ?? ''] ?? null;
                $found[$line->key] = self::line($size !== null ? "Size: {$size}" : null);
            }
        }

        return $found;
    }

    public function checkOptions(int $ref, array $options, string $locale): array
    {
        return isset(self::SIZES[$options['size'] ?? '']) ? [] : ['Choose a size.'];
    }

    public function find(string $term, string $locale, int $limit): array
    {
        if ($term !== '' && ! str_contains('shirt', mb_strtolower($term))) {
            return [];
        }

        $values = [];

        foreach (self::SIZES as $value => $label) {
            $values[] = ['value' => $value, 'label' => $label];
        }

        return [new PurchasableChoice(1, self::line(null), [new ChoiceOption('size', 'Size', $values)], 'Shirts')];
    }

    private static function line(?string $label): PurchasableLine
    {
        return new PurchasableLine(name: 'Shirt', sku: 'SHIRT-1', price: 2000, currency: 'EUR', optionsLabel: $label);
    }
}
