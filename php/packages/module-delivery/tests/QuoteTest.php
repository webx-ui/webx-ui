<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use WebxUi\Delivery\Payer;
use WebxUi\Delivery\Quote;
use WebxUi\Delivery\QuoteKind;

/** The price kinds of §9.1 and what each puts into the total and onto the waybill (§9.3). */
final class QuoteTest extends PhpUnitTestCase
{
    #[Test]
    public function a_fixed_price_goes_into_the_total_and_the_shop_pays_the_carrier(): void
    {
        $quote = Quote::fixed(1500);

        $this->assertSame(QuoteKind::Fixed, $quote->kind);
        $this->assertSame(1500, $quote->amount);
        $this->assertTrue($quote->inTotal);
        $this->assertSame(Payer::Store, $quote->payer);
        $this->assertSame(['kind' => 'fixed', 'price' => 1500, 'was' => null, 'estimate' => null, 'in_total' => true, 'payer' => 'store'], $quote->toArray());
    }

    #[Test]
    public function zero_is_free_without_a_crossed_out_price(): void
    {
        $quote = Quote::fixed(0);

        $this->assertSame(QuoteKind::Free, $quote->kind);
        $this->assertNull($quote->was);
        $this->assertFalse($quote->inTotal);
        $this->assertSame(0, $quote->toArray()['price']);
    }

    #[Test]
    public function free_keeps_the_price_it_would_have_been(): void
    {
        $quote = Quote::free(1500, 'paid by the shop');

        $this->assertSame(1500, $quote->was);
        $this->assertFalse($quote->inTotal);
        $this->assertSame(Payer::Store, $quote->payer);
    }

    #[Test]
    public function carrier_rates_stay_out_of_the_total_and_the_buyer_pays_the_carrier(): void
    {
        $quote = Quote::carrierRates(7000);

        $this->assertNull($quote->amount);
        $this->assertSame(7000, $quote->estimate);
        $this->assertFalse($quote->inTotal);
        $this->assertSame(Payer::Buyer, $quote->payer);
        $this->assertNull($quote->toArray()['price']);
    }

    #[Test]
    public function unavailable_carries_its_reason(): void
    {
        $quote = Quote::unavailable('No branch takes parcels this heavy');

        $this->assertFalse($quote->available());
        $this->assertSame('No branch takes parcels this heavy', $quote->reason);
    }

    #[Test]
    public function a_price_is_never_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Quote::fixed(-1);
    }

    #[Test]
    public function paid_by_store_makes_a_price_free_with_it_crossed_out_and_the_sender_paying(): void
    {
        foreach ([[Quote::fixed(700), 700], [Quote::carrierRates(7000), 7000], [Quote::carrierRates(), null]] as [$quote, $was]) {
            $free = $quote->paidByStore();

            $this->assertSame(QuoteKind::Free, $free->kind);
            $this->assertSame($was, $free->was);
            $this->assertSame(Payer::Store, $free->payer);
            $this->assertFalse($free->inTotal);
            $this->assertTrue($free->isPaidByStore());
        }

        // Free already, or not offered: nothing to give.
        $this->assertFalse(Quote::free()->paidByStore()->isPaidByStore());
        $this->assertFalse(Quote::unavailable('Too heavy')->paidByStore()->available());
    }

    #[Test]
    public function a_stored_quote_comes_back_the_same(): void
    {
        foreach ([Quote::fixed(700), Quote::free(700), Quote::carrierRates(7000), Quote::unavailable('Too heavy')] as $quote) {
            $this->assertEquals($quote, Quote::fromStored($quote->toStored()));
        }

        $this->assertFalse(Quote::fromStored([])->available());
    }
}
