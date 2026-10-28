<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests\Fakes;

use WebxUi\Cart\Contracts\ChecksOptions;
use WebxUi\Cart\Contracts\Purchasable;
use WebxUi\Cart\Contracts\ResolvesWithOptions;
use WebxUi\Cart\PurchasableLine;

/**
 * A provider whose price depends on the choice: a size from 1 to 3 adds 10.00 a step to the
 * record's 100.00. Record 9 is gone.
 */
final class FakeConfigurations implements ChecksOptions, Purchasable, ResolvesWithOptions
{
    /** @var list<int> how many lines each batch held */
    public array $calls = [];

    public function resolve(array $refs, string $locale): array
    {
        return [];
    }

    public function resolveLines(array $lines, string $locale): array
    {
        $this->calls[] = count($lines);
        $found = [];

        foreach ($lines as $line) {
            if ($line->ref === 9) {
                continue;
            }

            $size = is_int($line->options['size'] ?? null) ? $line->options['size'] : 0;
            $found[$line->key] = new PurchasableLine(
                name: "Table {$line->ref}",
                sku: null,
                price: 10000 + 1000 * $size,
                currency: 'EUR',
                optionsLabel: $size > 0 ? "Size: {$size}" : null,
            );
        }

        return $found;
    }

    public function checkOptions(int $ref, array $options, string $locale): array
    {
        $size = $options['size'] ?? null;

        return is_int($size) && $size >= 1 && $size <= 3 ? [] : ['The size is from 1 to 3.'];
    }
}
