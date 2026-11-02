<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Delivery\Benefits;
use WebxUi\Delivery\Delivery;
use WebxUi\Delivery\DeliveryException;

final class DeliveryTest extends TestCase
{
    #[Test]
    public function without_a_shippable_line_there_is_no_delivery_step(): void
    {
        $this->assertFalse(Delivery::needed($this->cart([])));
        $this->assertFalse(Delivery::needed($this->cart([[false], [false]])));
        $this->assertTrue(Delivery::needed($this->cart([[false], [true]])));
    }

    #[Test]
    public function a_line_that_cannot_be_bought_or_is_gone_does_not_travel(): void
    {
        $this->assertFalse(Delivery::needed($this->cart([[true, false], null])));
        $this->assertCount(1, Delivery::shippableLines($this->cart([[true, false], null, [true], [false]])));
    }

    #[Test]
    public function the_context_copies_the_locality_and_reads_the_cart(): void
    {
        $town = $this->town('Town A', 'Region B');
        $cart = $this->cart([[true], [false]], [Benefits::PAID_BY_STORE]);

        $context = $this->app->make(Delivery::class)->context($cart, $town->id, Purpose::Order);

        $this->assertSame('XX', $context->country);
        $this->assertSame($town->id, $context->locality->id);
        $this->assertSame('Town A, Region B', $context->locality->label('en'));
        $this->assertNull($context->carrierLocality);
        $this->assertSame('EUR', $context->currency);
        $this->assertSame(Purpose::Order, $context->purpose);
        $this->assertCount(1, $context->shippableLines());
        $this->assertTrue($context->paidByStore());
        $this->assertFalse($this->context($town)->paidByStore());
    }

    #[Test]
    public function an_unknown_locality_is_refused_in_words(): void
    {
        $this->expectException(DeliveryException::class);
        $this->expectExceptionMessage('No locality with id 999.');

        $this->app->make(Delivery::class)->context($this->cart(), 999);
    }
}
