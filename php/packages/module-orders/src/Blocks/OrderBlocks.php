<?php

declare(strict_types=1);

namespace WebxUi\Orders\Blocks;

use InvalidArgumentException;

/**
 * The blocks of the snapshot (§6.4): two columns, `delivery` and `payment`, each filled by the
 * module that is installed. A module registers its block in boot(), under
 * class_exists(OrderBlocks::class); no other key is taken — a third block is a new column and a
 * decision, not a registration.
 */
final class OrderBlocks
{
    /** The columns of `orders` a block lands in. */
    public const array KEYS = ['delivery', 'payment'];

    /** @var array<string, BlockDefinition> */
    private array $blocks = [];

    public function register(BlockDefinition $block): void
    {
        if (! in_array($block->key, self::KEYS, true)) {
            throw new InvalidArgumentException("An order has no block «{$block->key}»: only ".implode(', ', self::KEYS).'.');
        }

        $this->blocks[$block->key] = $block;
    }

    public function has(string $key): bool
    {
        return isset($this->blocks[$key]);
    }

    public function get(string $key): ?BlockDefinition
    {
        return $this->blocks[$key] ?? null;
    }

    /**
     * Each block passed, as its owner accepts it.
     *
     * @param  array<array-key, mixed>  $blocks
     *
     * @throws BlockRefused
     */
    public function check(array $blocks): void
    {
        foreach ($blocks as $key => $value) {
            $block = is_string($key) ? $this->get($key) : null;

            if ($block === null) {
                throw new BlockRefused((string) $key, ['no module registered it']);
            }

            if (! is_array($value)) {
                throw new BlockRefused($block->key, ['a block is an array']);
            }

            /** @var array<string, mixed> $value */
            $errors = ($block->validate)($value);

            if ($errors !== []) {
                throw new BlockRefused($block->key, array_values(array_map(strval(...), $errors)));
            }
        }
    }

    /**
     * The block's one line in the language asked, or null when its owner gave no summary.
     *
     * @param  array<string, mixed>  $value
     */
    public function summary(string $key, array $value, string $locale): ?string
    {
        $summary = $this->get($key)?->summary;

        return $summary !== null ? (string) $summary($value, $locale) : null;
    }
}
