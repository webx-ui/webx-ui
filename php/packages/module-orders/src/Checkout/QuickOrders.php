<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use WebxUi\Cart\CartService;
use WebxUi\Orders\CartNotOrderable;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrderRefused;
use WebxUi\Orders\Orders;
use WebxUi\Orders\PlaceOrder;

/**
 * «Buy in one click» (spec §29, decision 62): an order of one line without the buyer's cart. The
 * line goes into a cart of its own — a row of `carts` whose token nobody is given, so no cookie
 * carries it (§29.1) — through the same path as «Add to cart», the order is placed of it as of any
 * other, and the cart goes. One transaction: a refusal at any step leaves neither the cart nor
 * the order, and the buyer's own cart and draft are never read.
 *
 * Through a cart and not a cart in memory: `Orders::place()` locks the row, counts its lines for
 * the order and ties the adjustments to them — the rules, gifts and privileges of the cart work
 * here as they do on the checkout, with no second way into the order.
 */
final readonly class QuickOrders
{
    public const string SOURCE = 'quick';

    public function __construct(
        private CartService $carts,
        private Orders $orders,
        private CheckoutTerms $terms,
        private ConnectionInterface $db,
    ) {}

    /**
     * The order of one line. A repeat of the same key is the order the key made, and nothing new.
     *
     * @param  array{type: string, ref: int|string, qty: int, options: array<array-key, mixed>}  $line
     *
     * @throws ValidationException the line cannot go into a cart (gone, options, quantity)
     * @throws CartNotOrderable|OrderRefused
     */
    public function place(array $line, Contact $customer, string $locale, ?int $userId, string $key): Order
    {
        return $this->db->transaction(function () use ($line, $customer, $locale, $userId, $key): Order {
            $cart = $this->carts->add(null, [$line], $locale)->cart;

            // No amount to compare with (§29.1): the price was on the product's page, and the
            // dialog answers with the amount the order came to.
            $order = $this->orders->place(new PlaceOrder(
                cart: $cart,
                locale: $locale,
                customer: $customer,
                idempotencyKey: $key,
                userId: $userId,
                source: self::SOURCE,
            ));

            if ($order->wasRecentlyCreated) {
                $this->terms->record($order, null, $this->orders, CarbonImmutable::now());
            }

            $this->carts->clear($cart);

            return $order;
        });
    }
}
