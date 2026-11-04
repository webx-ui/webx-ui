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
     * @param  (Closure(array<string, mixed>, Order): bool)|null  $held  the buyer's money is in the shop's hands though the
     *                                                                   order is not paid yet — a hold the shop captures later
     *                                                                   (§8.3 of payments): for the buyer it is paid, so their
     *                                                                   pages do not say «waiting for payment»
     * @param  (Closure(array<string, mixed>, Order, string): ?array{title: string, html: string})|null  $prompt  an order that
     *                                                                                                            awaits payment and the buyer can pay now: its page
     *                                                                                                            says `title` over the public status and shows `html`,
     *                                                                                                            the way to pay; null — the order just waits
     * @param  (Closure(list<Order>, string): array<int, string>)|null  $awaiting  the same rule for a list, «My orders»: of
     *                                                                             the orders passed, the id of each the buyer can
     *                                                                             pay now and the `title` it reads over the public
     *                                                                             status — all at once, a list asks nothing per row
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
        public ?Closure $held = null,
        public ?Closure $prompt = null,
        public ?Closure $awaiting = null,
    ) {}
}
