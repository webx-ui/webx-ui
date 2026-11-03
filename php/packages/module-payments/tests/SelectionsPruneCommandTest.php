<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payments\Models\PaymentSelection;
use WebxUi\Payments\Tests\Checkout\PaymentsCheckoutCase;

/** The night takes the choices of carts the cart's pruning removed without telling anybody (spec §5). */
final class SelectionsPruneCommandTest extends PaymentsCheckoutCase
{
    #[Test]
    public function only_the_choices_of_gone_carts_go(): void
    {
        $cart = $this->cart()->cart;
        PaymentSelection::query()->create(['cart_id' => $cart->id, 'method_key' => 'bank-transfer']);
        PaymentSelection::query()->create(['cart_id' => $cart->id + 1000, 'method_key' => 'on-receipt']);

        $this->artisan('webx:payments:selections-prune')->expectsOutputToContain('1 payment choice of gone carts removed.')->assertSuccessful();

        $this->assertSame([$cart->id], PaymentSelection::query()->pluck('cart_id')->all());
    }
}
