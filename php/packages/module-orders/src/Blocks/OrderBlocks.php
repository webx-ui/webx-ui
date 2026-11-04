<?php

declare(strict_types=1);

namespace WebxUi\Orders\Blocks;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;
use WebxUi\Orders\Models\Order;

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

    /**
     * What the buyer reads of a block under its line (§13.1), as HTML: null — its owner has nothing
     * more to say. An owner that throws says nothing: the order's page does not go down with it.
     *
     * @param  array<string, mixed>  $value
     */
    public function details(string $key, array $value, Order $order, string $locale): ?string
    {
        $details = $this->get($key)?->details;

        if ($details === null) {
            return null;
        }

        try {
            $html = $details($value, $order, $locale);
        } catch (Throwable $e) {
            Log::warning("webx-orders: the details of the block «{$key}» failed", ['exception' => $e]);

            return null;
        }

        return is_string($html) && trim($html) !== '' ? $html : null;
    }

    /**
     * The way to pay an order that awaits payment (§13.1): the first owner that has one; an owner
     * that throws says nothing.
     *
     * @return array{title: string, html: string}|null
     */
    public function prompt(Order $order, string $locale): ?array
    {
        foreach ($this->blocks as $key => $block) {
            $value = $order->getAttribute($key);

            if ($block->prompt === null || ! is_array($value)) {
                continue;
            }

            try {
                $prompt = ($block->prompt)($value, $order, $locale);
            } catch (Throwable $e) {
                Log::warning("webx-orders: the prompt of the block «{$key}» failed", ['exception' => $e]);

                continue;
            }

            if ($prompt !== null) {
                return $prompt;
            }
        }

        return null;
    }

    /**
     * Whether a block says the buyer's money is in the shop's hands though the order is not paid
     * yet — a hold (§8.3 of payments): the buyer's pages then do not say «waiting for payment». An
     * owner that throws says no.
     */
    public function held(Order $order): bool
    {
        foreach ($this->blocks as $key => $block) {
            $value = $order->getAttribute($key);

            if ($block->held === null || ! is_array($value)) {
                continue;
            }

            try {
                if (($block->held)($value, $order) === true) {
                    return true;
                }
            } catch (Throwable $e) {
                Log::warning("webx-orders: the hold of the block «{$key}» failed", ['exception' => $e]);
            }
        }

        return false;
    }

    /**
     * The block's price in words when no adjustment of the order carries it (§11.3 of the card):
     * null — its owner gave no words, or the block has no price.
     *
     * @param  array<string, mixed>  $value
     */
    public function price(string $key, array $value, string $locale): ?string
    {
        $price = $this->get($key)?->price;

        return $price !== null ? $price($value, $locale) : null;
    }

    /**
     * The adjustment sources that are a block's price, by block: `delivery` → `delivery`.
     *
     * @return array<string, string>
     */
    public function charges(): array
    {
        $charges = [];

        foreach ($this->blocks as $key => $block) {
            if ($block->charge !== null) {
                $charges[$key] = $block->charge;
            }
        }

        return $charges;
    }
}
