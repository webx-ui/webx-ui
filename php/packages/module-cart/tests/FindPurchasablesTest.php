<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\CartService;
use WebxUi\Cart\PurchasableChoice;
use WebxUi\Cart\Purchasables;

/** `Purchasables::find()`: only the providers that can be searched are asked. */
final class FindPurchasablesTest extends TestCase
{
    #[Test]
    public function it_asks_only_the_providers_that_find(): void
    {
        $found = $this->app->make(Purchasables::class)->find('tab', 'en', 10);

        // `test.product` has no search: left out, not asked for an empty answer.
        $this->assertSame(['test.configuration'], array_keys($found));
        $this->assertSame([1, 2], array_map(static fn (PurchasableChoice $choice): int => $choice->ref, $found['test.configuration']));
        $this->assertSame('size', $found['test.configuration'][0]->options[0]->key);
        $this->assertSame([], $this->products->calls);
    }

    #[Test]
    public function a_provider_with_nothing_found_is_left_out(): void
    {
        $this->assertSame([], $this->app->make(Purchasables::class)->find('chair', 'en', 10));
    }

    #[Test]
    public function a_value_offered_goes_into_a_line_as_it_is(): void
    {
        $choice = $this->app->make(Purchasables::class)->find('table', 'en', 1)['test.configuration'][0];
        $value = $choice->options[0]->values[1]['value'];

        $cart = $this->app->make(CartService::class)
            ->add(null, [['type' => 'test.configuration', 'ref' => $choice->ref, 'options' => [$choice->options[0]->key => $value]]], 'en')
            ->cart;

        $this->assertSame(['size' => 2], $cart->items()->sole()->options);
    }
}
