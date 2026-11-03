<?php

declare(strict_types=1);

namespace WebxUi\Orders\Blocks;

use Closure;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Orders;

/**
 * A block of the snapshot and the module that fills it (§6.4): the order checks the block's shape
 * by its owner's hand and shows its line in the list and letters without knowing the methods.
 */
final readonly class BlockDefinition
{
    /**
     * @param  string  $key  'delivery' | 'payment' — the column it lands in
     * @param  string  $module  'delivery'
     * @param  Closure(array<string, mixed>): list<string>  $validate  errors; empty — accepted
     * @param  (Closure(array<string, mixed>, string): string)|null  $summary  one line for the list and mail
     * @param  string|null  $charge  the `source` of the order's adjustment that is the block's price —
     *                               `delivery`: the card's «Summary» shows it as a row of its own, not
     *                               among the discounts and charges, and {@see Orders::changeBlock()} replaces it
     * @param  (Closure(array<string, mixed>, string): ?string)|null  $price  the block's price in words when
     *                                                                        no adjustment carries it — «Free», «By carrier rates»
     * @param  string|null  $view  the right that opens the owner's own view of the block on the card
     *                             (its tab): whoever has it is not shown the block's raw data under «Items»
     * @param  (Closure(array<string, mixed>, Order, string): ?string)|null  $details  what the buyer reads of the
     *                                                                                 block under its line on the order's page and
     *                                                                                 «Thank you», as HTML — the bank details of a
     *                                                                                 transfer; null — the line says it all
     */
    public function __construct(
        public string $key,
        public string $module,
        public Closure $validate,
        public ?Closure $summary = null,
        public ?string $charge = null,
        public ?Closure $price = null,
        public ?string $view = null,
        public ?Closure $details = null,
    ) {}
}
