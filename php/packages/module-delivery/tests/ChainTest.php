<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Cart\BenefitKeys;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Purchasables;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Cart\Totals\AdjustmentSource;
use WebxUi\Cart\Totals\AdjustmentSources;
use WebxUi\Cart\Totals\Benefit;
use WebxUi\Cart\Totals\BenefitSource;
use WebxUi\Cart\Totals\BenefitSources;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\CartContext;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Cart\Totals\Tender;
use WebxUi\Cart\Totals\TenderSource;
use WebxUi\Cart\Totals\TenderSources;
use WebxUi\Delivery\Benefits;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\CartDelivery;
use WebxUi\Delivery\Delivery;
use WebxUi\Delivery\DeliveryAdjustments;
use WebxUi\Delivery\DeliveryCost;
use WebxUi\Delivery\LocalityRef;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\MethodOption;
use WebxUi\Delivery\Methods;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliverySelection;
use WebxUi\Delivery\Quote;
use WebxUi\Delivery\QuoteFailed;
use WebxUi\Delivery\QuoteKind;
use WebxUi\Delivery\Selection;
use WebxUi\Delivery\Tests\Fakes\FakeCarrierA;
use WebxUi\Delivery\Tests\Fakes\FakeDoorType;
use WebxUi\Delivery\Tests\Fakes\FakeGoods;
use WebxUi\Delivery\Types\StorePickup;
use WebxUi\Geography\Models\GeoLocality;

/**
 * Delivery in the cart's chain (§9.3, §9.4): which price is a line of the total, what the shop
 * pays under `shipping.paid_by_store`, where it stands among discounts and the wallet, and how a
 * carrier's price is cached and fails — on a real cart, counted by the cart's own calculator.
 */
final class ChainTest extends TestCase
{
    private GeoLocality $town;

    protected function setUp(): void
    {
        parent::setUp();

        FakeDoorType::$answer = null;
        FakeDoorType::$calls = 0;
        $this->app->make(Purchasables::class)->register('test.goods', new FakeGoods);
        $this->town = $this->town('Town A');
        $this->point($this->town);
    }

    /** @param  list<array<string, mixed>>  $items */
    private function cartOf(array $items = [['type' => 'test.goods', 'ref' => 1]]): Cart
    {
        return $this->app->make(CartService::class)->add(null, $items, 'en')->cart;
    }

    private function choose(Cart $cart, DeliveryMethod $method): void
    {
        DeliverySelection::query()->updateOrCreate(['cart_id' => $cart->id], [
            'country_code' => 'XX',
            'locality_id' => $this->town->id,
            'method_id' => $method->id,
        ]);
        $this->app->make(CartDelivery::class)->forget($cart->id);
    }

    private function counted(Cart $cart, Purpose $purpose = Purpose::Checkout): CartState
    {
        return $this->app->make(Calculator::class)->calculate($cart, 'en', $purpose);
    }

    private function cost(Cart $cart, CartState $state, Purpose $purpose = Purpose::Checkout): ?DeliveryCost
    {
        return $this->app->make(Delivery::class)->cost($state, $cart->id, $purpose);
    }

    /** @param  array<string, mixed>  $attributes */
    private function pickup(array $attributes = []): DeliveryMethod
    {
        return $this->method('store-pickup', StorePickup::KEY, ['point_kind' => 'store', ...$attributes]);
    }

    private function paidByStore(): void
    {
        $this->app->make(BenefitSources::class)->register(new class implements BenefitSource
        {
            public function benefits(CartContext $cart): array
            {
                return [new Benefit(Benefits::PAID_BY_STORE, 'Free delivery', 'rule#1')];
            }
        });
    }

    private function door(): DeliveryMethod
    {
        $this->app->make(Carriers::class)->register(FakeCarrierA::class);
        $this->app->make(MethodTypes::class)->register(FakeDoorType::class);
        DeliveryCarrier::query()->create(['key' => 'carrier-a', 'enabled' => true]);
        DeliveryCarrierLocality::query()->create([
            'carrier' => 'carrier-a',
            'external_id' => 'a-1',
            'locality_id' => $this->town->id,
            'status' => 'matched',
            'method' => 'auto_exact',
            'record' => ['name' => 'Town A'],
            'features' => ['door'],
        ]);

        return $this->method('carrier-a-door', 'carrier-a.door');
    }

    /** @return list<Adjustment> */
    private static function delivery(CartState $state): array
    {
        return array_values(array_filter($state->adjustments, static fn (Adjustment $a): bool => $a->source === DeliveryAdjustments::SOURCE));
    }

    #[Test]
    public function a_fixed_price_is_a_line_of_the_total_and_of_what_is_paid(): void
    {
        $cart = $this->cartOf();
        $this->choose($cart, $this->pickup(['price' => 700]));

        $state = $this->counted($cart);

        [$line] = self::delivery($state);
        $this->assertSame(700, $line->amount);
        $this->assertSame('Delivery', $line->label);
        $this->assertNull($line->line);
        $this->assertSame(['kind' => 'delivery', 'method' => 'store-pickup', 'type' => 'store.pickup', 'quote' => 'fixed'], $line->meta);
        $this->assertSame(5000, $state->subtotal);
        $this->assertSame(5700, $state->total);
        $this->assertSame(5700, $state->payable);

        $cost = $this->cost($cart, $state);
        $this->assertNotNull($cost);
        $this->assertTrue($cost->inTotal());
        $this->assertSame(['method' => 'store-pickup', 'label' => 'Delivery cost', 'kind' => 'fixed', 'text' => "7,00\u{00A0}€", 'amount' => 700, 'in_total' => true, 'paid_by_store' => false],
            array_intersect_key($cost->toArray(), array_flip(['method', 'label', 'kind', 'text', 'amount', 'in_total', 'paid_by_store'])));
        $this->assertSame(Quote::fixed(700)->payer, $cost->quote->payer);
    }

    #[Test]
    public function under_paid_by_store_the_price_is_free_with_the_old_one_crossed_out_and_no_line(): void
    {
        $this->paidByStore();
        $cart = $this->cartOf();
        $this->choose($cart, $this->pickup(['price' => 700]));

        $state = $this->counted($cart);

        $this->assertSame([], self::delivery($state));
        $this->assertSame(5000, $state->total);
        $this->assertSame(5000, $state->payable);
        $this->assertTrue($state->hasBenefit(Benefits::PAID_BY_STORE));

        $cost = $this->cost($cart, $state)?->toArray();
        $this->assertSame('free', $cost['kind'] ?? null);
        $this->assertSame('Free', $cost['text']);
        $this->assertSame(700, $cost['was']);
        $this->assertSame("7,00\u{00A0}€", $cost['was_formatted']);
        $this->assertFalse($cost['in_total']);
        $this->assertTrue($cost['paid_by_store']);
    }

    #[Test]
    public function a_method_that_does_not_honour_paid_by_store_stays_paid(): void
    {
        $this->paidByStore();
        $cart = $this->cartOf();
        $this->choose($cart, $this->pickup(['price' => 700, 'honours_paid_by_store' => false]));

        $state = $this->counted($cart);

        $this->assertSame(700, self::delivery($state)[0]->amount ?? null);
        $this->assertSame(5700, $state->payable);
        $this->assertFalse($this->cost($cart, $state)?->quote->isPaidByStore());
    }

    #[Test]
    public function a_free_method_is_free_without_a_crossed_out_price_and_no_line(): void
    {
        $cart = $this->cartOf();
        $this->choose($cart, $this->pickup());

        $state = $this->counted($cart);

        $this->assertSame([], self::delivery($state));
        $this->assertSame(5000, $state->payable);
        $cost = $this->cost($cart, $state)?->toArray();
        $this->assertSame(['free', 'Free', null, false, false], [$cost['kind'] ?? null, $cost['text'], $cost['was'], $cost['in_total'], $cost['paid_by_store']]);
    }

    #[Test]
    public function by_carrier_rates_is_not_in_the_total_and_the_estimate_is_for_information(): void
    {
        FakeDoorType::$answer = static fn (): Quote => Quote::carrierRates(7000);
        $cart = $this->cartOf();
        $this->choose($cart, $this->door());

        $state = $this->counted($cart);

        $this->assertSame([], self::delivery($state));
        $this->assertSame(5000, $state->payable);
        $cost = $this->cost($cart, $state)?->toArray();
        $this->assertSame(['carrier_rates', 'By carrier rates', null, 7000, "70,00\u{00A0}€", false], [
            $cost['kind'] ?? null, $cost['text'], $cost['amount'], $cost['estimate'], $cost['estimate_formatted'], $cost['in_total'],
        ]);
    }

    #[Test]
    public function by_carrier_rates_under_paid_by_store_is_free_with_the_estimate_crossed_out(): void
    {
        $this->paidByStore();
        FakeDoorType::$answer = static fn (): Quote => Quote::carrierRates(7000);
        $cart = $this->cartOf();
        $this->choose($cart, $this->door());

        $cost = $this->cost($cart, $this->counted($cart));

        $this->assertSame(QuoteKind::Free, $cost?->quote->kind);
        $this->assertSame(7000, $cost->quote->was);
        $this->assertTrue($cost->quote->isPaidByStore());
    }

    #[Test]
    public function a_discount_in_per_cent_does_not_touch_delivery_and_the_wallet_pays_for_it_too(): void
    {
        $sources = $this->app->make(AdjustmentSources::class);
        // Registered after delivery, but with a lower number: it runs first, as every discount does.
        $sources->register(new class implements AdjustmentSource
        {
            public function adjustments(CartContext $cart): array
            {
                return [new Adjustment('rule#5', '10% off', -intdiv($cart->subtotal, 10))];
            }
        }, 10);
        $this->app->make(TenderSources::class)->register(new class implements TenderSource
        {
            public function tenders(CartContext $cart): array
            {
                return [new Tender('wallet', 'Gift card', 100000)];
            }
        });
        $cart = $this->cartOf();
        $this->choose($cart, $this->pickup(['price' => 700]));

        $state = $this->counted($cart);

        $this->assertSame(['rule#5', 'delivery'], array_map(static fn (Adjustment $a): string => $a->source, $state->adjustments));
        $this->assertSame(-500, $state->adjustments[0]->amount, 'ten per cent of the goods, not of the delivery');
        $this->assertSame(700, $state->adjustments[1]->amount);
        $this->assertSame(5200, $state->total);
        // The certificate covers the delivery as well: nothing is left to pay.
        $this->assertSame(5200, $state->tenders[0]->amount);
        $this->assertSame(0, $state->payable);
    }

    #[Test]
    public function the_widget_of_the_cart_and_a_cart_without_a_choice_have_no_delivery(): void
    {
        $method = $this->pickup(['price' => 700]);
        $cart = $this->cartOf();

        $this->assertSame([], self::delivery($this->counted($cart)));
        $this->assertNull($this->cost($cart, $this->counted($cart)));

        $this->choose($cart, $method);

        $this->assertSame([], self::delivery($this->counted($cart, Purpose::Cart)));
        $this->assertSame(700, self::delivery($this->counted($cart, Purpose::Order))[0]->amount ?? null);
    }

    #[Test]
    public function nothing_that_travels_no_delivery_in_the_total(): void
    {
        $cart = $this->cartOf([['type' => 'test.goods', 'ref' => 2]]);
        $this->choose($cart, $this->pickup(['price' => 700]));

        $state = $this->counted($cart);

        $this->assertSame([], self::delivery($state));
        $this->assertSame(2000, $state->payable);
        $this->assertNull($this->cost($cart, $state));
    }

    #[Test]
    public function a_carriers_price_is_cached_for_the_same_cart_and_asked_again_when_the_cart_changes(): void
    {
        $cart = $this->cartOf();
        $this->choose($cart, $this->door());

        $this->assertSame(900, self::delivery($this->counted($cart))[0]->amount ?? null);
        $this->counted($cart);
        $this->assertSame(1, FakeDoorType::$calls, 'the same step shown again does not call the API');

        $this->app->make(CartService::class)->add($cart, [['type' => 'test.goods', 'ref' => 1]], 'en');
        $this->counted($cart->refresh());
        $this->assertSame(2, FakeDoorType::$calls, 'another cart is another price');
    }

    #[Test]
    public function the_shops_own_price_is_never_cached(): void
    {
        $cart = $this->cartOf();
        $method = $this->pickup(['price' => 700]);
        $this->choose($cart, $method);
        $this->counted($cart);

        $method->update(['price' => 300]);

        $this->assertSame(300, self::delivery($this->counted($cart))[0]->amount ?? null);
    }

    #[Test]
    public function a_carrier_that_fails_on_the_checkout_is_by_carrier_rates_and_stays_in_the_list(): void
    {
        $log = Log::spy();
        FakeDoorType::$answer = static fn (): Quote => throw new RuntimeException('Timed out after 2 s');
        $cart = $this->cartOf();
        $this->choose($cart, $this->door());

        $state = $this->counted($cart);

        $this->assertSame([], self::delivery($state));
        $this->assertSame([], $state->warnings, 'the chain is whole: the method is priced «By carrier rates»');
        $this->assertSame(QuoteKind::CarrierRates, $this->cost($cart, $state)?->quote->kind);

        $offered = $this->app->make(Methods::class)->forLocality($this->context($this->town, $state));
        $this->assertSame(['carrier-a-door'], array_map(static fn ($option): string => $option->method->key, $offered));
        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => str_contains($message, 'carrier-a-door'))->atLeast()->once();
    }

    #[Test]
    public function making_an_order_neither_reads_the_cache_nor_falls_back(): void
    {
        $cart = $this->cartOf();
        $this->choose($cart, $this->door());
        $this->counted($cart);
        $this->assertSame(1, FakeDoorType::$calls);

        $this->assertSame(900, self::delivery($this->counted($cart, Purpose::Order))[0]->amount ?? null);
        $this->assertSame(2, FakeDoorType::$calls, 'the order asks the carrier itself');

        FakeDoorType::$answer = static fn (): Quote => throw new RuntimeException('Timed out after 2 s');

        try {
            $this->counted($cart, Purpose::Order);
            $this->fail('An order was counted without the price of its delivery.');
        } catch (QuoteFailed $e) {
            $this->assertSame('carrier-a-door', $e->method);
            $this->assertSame('Could not calculate delivery, choose another method.', $e->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
        }
    }

    #[Test]
    public function a_chosen_method_no_longer_offered_is_no_line_on_the_checkout_and_no_order(): void
    {
        $cart = $this->cartOf();
        $method = $this->pickup(['price' => 700]);
        $this->choose($cart, $method);
        $method->update(['enabled' => false]);

        $this->assertSame([], self::delivery($this->counted($cart)));
        $this->assertNull($this->cost($cart, $this->counted($cart)));

        $this->expectException(QuoteFailed::class);
        $this->counted($cart, Purpose::Order);
    }

    #[Test]
    public function the_methods_of_a_locality_show_paid_by_store_too(): void
    {
        $this->paidByStore();
        $this->pickup(['price' => 700]);
        $cart = $this->cartOf();
        $state = $this->counted($cart);

        [$option] = $this->app->make(Methods::class)->forLocality($this->context($this->town, $state));

        $this->assertSame(QuoteKind::Free, $option->quote?->kind);
        $this->assertSame(700, $option->quote->was);
    }

    #[Test]
    public function delivery_registers_its_benefit_for_the_cart_rules_and_its_source_after_the_discounts(): void
    {
        $keys = $this->app->make(BenefitKeys::class);

        $this->assertTrue($keys->has(Benefits::PAID_BY_STORE));
        $this->assertSame([Benefits::PAID_BY_STORE => 'The shop pays for delivery'], $keys->options('en'));
        $this->assertSame([Benefits::PAID_BY_STORE => 'Sklep płaci za dostawę'], $keys->options('pl'));

        $chain = $this->app->make(AdjustmentSources::class)->describe();
        $this->assertCount(1, $chain);
        $this->assertInstanceOf(DeliveryAdjustments::class, $chain[0]['source']);
        $this->assertSame(1000, $chain[0]['priority']);
    }

    #[Test]
    public function the_price_of_a_selection_is_the_one_the_chain_counts_or_why_it_is_not_offered(): void
    {
        $this->paidByStore();
        $method = $this->pickup(['price' => 700]);
        $delivery = $this->app->make(Delivery::class);
        $state = $this->counted($this->cartOf());
        $selection = new Selection(Method::of($method), LocalityRef::of($this->town));

        $quote = $delivery->quoteFor($selection, $this->context($this->town, $state));
        $this->assertTrue($quote->isPaidByStore());
        $this->assertSame(700, $quote->was);

        $elsewhere = $this->town('Town B');
        $quote = $delivery->quoteFor($selection, $this->context($elsewhere, $state));
        $this->assertFalse($quote->available());
        $this->assertSame(MethodOption::NO_POINTS, $quote->reason);
    }

    #[Test]
    public function the_selection_goes_with_its_cart(): void
    {
        $cart = $this->cartOf();
        $this->choose($cart, $this->pickup());

        $cart->delete();

        $this->assertSame(0, DeliverySelection::query()->count());
    }
}
