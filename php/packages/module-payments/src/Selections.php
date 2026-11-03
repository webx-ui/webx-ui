<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use WebxUi\Payments\Models\PaymentSelection;

/**
 * The method a buyer chose for a cart (spec §5, §10.2): one row a cart, written only by the
 * payment step's API, read once a request. `scoped`: what one request read is not the next one's.
 */
final class Selections
{
    /** @var array<int, string|null> by cart */
    private array $read = [];

    /** The chosen method's key; null — nothing chosen. */
    public function get(int $cart): ?string
    {
        if (! array_key_exists($cart, $this->read)) {
            $this->read[$cart] = PaymentSelection::query()->where('cart_id', $cart)->value('method_key');
        }

        return $this->read[$cart];
    }

    public function put(int $cart, string $method): void
    {
        PaymentSelection::query()->updateOrCreate(['cart_id' => $cart], ['method_key' => $method]);
        $this->read[$cart] = $method;
    }

    public function forget(int $cart): void
    {
        PaymentSelection::query()->where('cart_id', $cart)->delete();
        $this->read[$cart] = null;
    }
}
