<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Cart\Tests\Fakes\FakeSources;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Cart\Totals\AdjustmentSources;
use WebxUi\Cart\Totals\Benefit;
use WebxUi\Cart\Totals\BenefitSources;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\CartContext;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\GeneratedLine;
use WebxUi\Cart\Totals\Hint;
use WebxUi\Cart\Totals\HintSources;
use WebxUi\Cart\Totals\LineSources;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Cart\Totals\Tender;
use WebxUi\Cart\Totals\TenderSources;

final class CalculatorTest extends TestCase
{
    private const string NBSP = "\u{00A0}";

    /** @param  list<array<string, mixed>>  $items */
    private function cart(array $items): Cart
    {
        return $this->app->make(CartService::class)->add(null, $items, 'en')->cart;
    }

    private function state(Cart $cart, string $locale = 'en', Purpose $purpose = Purpose::Cart): CartState
    {
        return $this->app->make(Calculator::class)->calculate($cart, $locale, $purpose);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<string>
     */
    private static function codes(array $state): array
    {
        return array_column($state['warnings'], 'code');
    }

    #[Test]
    public function a_bundle_and_a_line_count_into_a_tree_with_live_prices(): void
    {
        $cart = $this->cart([
            ['type' => 'test.product', 'ref' => 1, 'qty' => 2, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 2, 'qty' => 3, 'parent' => 'set'],
            ['type' => 'test.product', 'ref' => 3],
        ]);

        // The price moved after the line went in: the cart shows today's and says so.
        $this->products->lines[3] = self::product('Item C', 45000);
        $this->products->calls = [];

        $state = $this->state($cart)->toArray();

        $this->assertCount(1, $this->products->calls, 'one call for the type, not one a line');
        $this->assertSame('EUR', $state['currency']);
        $this->assertSame(2, $state['exponent']);
        $this->assertCount(2, $state['lines']);

        [$set, $single] = $state['lines'];
        $this->assertSame('Item A', $set['name']);
        $this->assertSame(2, $set['effective_qty']);
        $this->assertSame(9998, $set['line_total']);
        $this->assertCount(1, $set['children']);
        $this->assertSame(3, $set['children'][0]['qty']);
        $this->assertSame(6, $set['children'][0]['effective_qty']);
        $this->assertSame(90000, $set['children'][0]['line_total']);
        $this->assertSame('900,00'.self::NBSP.'€', $set['children'][0]['line_total_formatted']);

        $this->assertSame(45000, $single['price']);
        $this->assertSame(48000, $single['price_at_add']);
        $this->assertSame('down', $single['price_change']);

        $this->assertSame(9, $state['units']);
        $this->assertSame(9998 + 90000 + 45000, $state['subtotal']);
        $this->assertSame('1'.self::NBSP.'449,98'.self::NBSP.'€', $state['subtotal_formatted']);
        $this->assertSame($state['subtotal'], $state['total']);
        $this->assertSame($state['subtotal'], $state['payable']);
        $this->assertSame(['price.down'], self::codes($state));
        $this->assertSame('Item C is now cheaper.', $state['warnings'][0]['message']);
    }

    #[Test]
    public function a_gone_record_shows_its_snapshot_name_and_counts_for_nothing(): void
    {
        $this->products->lines[7] = self::product('Item D', 9000, names: ['en' => 'Item D', 'pl' => 'Produkt D']);
        $this->products->lines[8] = self::product('Item E', 100);
        $cart = $this->cart([
            ['type' => 'test.product', 'ref' => 7],
            ['type' => 'test.product', 'ref' => 8],
            ['type' => 'test.product', 'ref' => 1],
        ]);
        unset($this->products->lines[7], $this->products->lines[8]);

        // A snapshot with neither the request's language nor the default one: whichever there is.
        CartItem::query()->where('ref_id', 8)->update(['name_at_add' => json_encode(['de' => 'Artikel E'])]);

        $polish = $this->state($cart, 'pl')->toArray();
        [$gone, $other] = $polish['lines'];
        $this->assertSame('Produkt D', $gone['name']);
        $this->assertTrue($gone['gone']);
        $this->assertFalse($gone['available']);
        $this->assertNull($gone['price']);
        $this->assertNull($gone['price_formatted']);
        $this->assertSame(9000, $gone['price_at_add']);
        $this->assertSame(0, $gone['line_total']);
        $this->assertSame('Artikel E', $other['name']);
        $this->assertSame(4999, $polish['subtotal']);
        $this->assertSame(['line.gone', 'line.gone'], self::codes($polish));
        $this->assertSame(3, $polish['units'], 'a gone line is still in the list, and in the counter');

        // A language the snapshot lacks falls back to the site's default.
        $this->assertSame('Item D', $this->state($cart, 'de')->toArray()['lines'][0]['name']);
    }

    #[Test]
    public function an_unregistered_type_is_gone_like_a_deleted_record(): void
    {
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1]]);
        CartItem::query()->update(['type' => 'removed.module']);

        $state = $this->state($cart)->toArray();

        $this->assertTrue($state['lines'][0]['gone']);
        $this->assertSame('Item A', $state['lines'][0]['name']);
        $this->assertSame(0, $state['subtotal']);
    }

    #[Test]
    public function price_on_request_a_foreign_currency_and_short_stock_are_warnings_not_repairs(): void
    {
        $cart = $this->cart([
            ['type' => 'test.product', 'ref' => 1, 'qty' => 5],
            ['type' => 'test.product', 'ref' => 2],
            ['type' => 'test.product', 'ref' => 3],
        ]);

        $this->products->lines[1] = self::product('Item A', 4999, maxQty: 2);
        $this->products->lines[2] = self::product('Item B', 0);
        $this->products->lines[3] = self::product('Item C', 48000, currency: 'USD');

        $state = $this->state($cart)->toArray();
        [$limited, $onRequest, $foreign] = $state['lines'];

        $this->assertSame(5, $limited['qty']);
        $this->assertSame(2, $limited['effective_qty']);
        $this->assertSame(9998, $limited['line_total']);
        $this->assertFalse($onRequest['available']);
        $this->assertFalse($onRequest['gone']);
        $this->assertSame('Item B', $onRequest['name']);
        $this->assertFalse($foreign['available']);
        $this->assertSame(9998, $state['subtotal']);
        $this->assertSame(['line.qty_limited', 'line.unavailable', 'line.currency'], self::codes($state));
        $this->assertSame(5, CartItem::query()->where('ref_id', 1)->value('qty'), 'reading never writes');
    }

    #[Test]
    public function a_provider_with_options_prices_each_line_by_its_choice(): void
    {
        $cart = $this->cart([
            ['type' => 'test.configuration', 'ref' => 5, 'options' => ['size' => 1]],
            ['type' => 'test.configuration', 'ref' => 5, 'options' => ['size' => 3]],
        ]);
        $this->configurations->calls = [];

        $state = $this->state($cart)->toArray();

        $this->assertSame([2], $this->configurations->calls);
        $this->assertSame([11000, 13000], array_column($state['lines'], 'price'));
        $this->assertSame(['Size: 1', 'Size: 3'], array_column($state['lines'], 'options_label'));
        $this->assertSame([['size' => 1], ['size' => 3]], array_column($state['lines'], 'options'));
    }

    #[Test]
    public function the_chain_runs_its_five_links_in_order_of_priority(): void
    {
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1, 'qty' => 2], ['type' => 'test.product', 'ref' => 2]]);
        $line = CartItem::query()->where('ref_id', 1)->value('id');

        $this->app->make(LineSources::class)->register(new FakeSources(static fn (): array => [
            new GeneratedLine('rule#1', 'test.product', 3, price: 1, label: 'Gift'),
            new GeneratedLine('rule#2', 'test.product', 3, parentId: $line, declinable: false),
        ]));

        $adjustments = $this->app->make(AdjustmentSources::class);
        $late = new FakeSources(static fn (CartContext $cart): array => [new Adjustment('coupon#SPRING', 'Coupon', -$cart->total / 10)]);
        $early = new FakeSources(static fn (): array => [new Adjustment('rule#3', 'Line discount', -999, $line)]);
        $adjustments->register($late, 10);
        $adjustments->register($early);

        $tenders = $this->app->make(TenderSources::class);
        $tenders->register(new FakeSources(static fn (): array => [new Tender('wallet', 'Balance', 10000)]));
        $this->app->make(BenefitSources::class)->register(new FakeSources(static fn (): array => [new Benefit('shipping.paid_by_store', 'Free shipping', 'rule#4')]));
        $this->app->make(HintSources::class)->register(new FakeSources(static fn (CartContext $cart): array => [new Hint('rule#5', 'Almost there', 100000 - $cart->total)]));

        $result = $this->state($cart);
        $state = $result->toArray();

        // Generated: one at the root, one under the stored line; their ids name the rule.
        $this->assertSame('rule#1:0', $state['lines'][2]['id']);
        $this->assertSame('Gift', $state['lines'][2]['label']);
        $this->assertTrue($state['lines'][2]['declinable']);
        $this->assertSame(1, $state['lines'][2]['price']);
        $this->assertSame('rule#2:0', $state['lines'][0]['children'][0]['id']);
        $this->assertSame(48000, $state['lines'][0]['children'][0]['price']);
        $this->assertSame(3, $state['units'], 'generated lines are not in the counter');

        $subtotal = 9998 + 15000 + 1 + 48000;
        $this->assertSame($subtotal, $state['subtotal']);

        // The early source saw nothing before it; the late one saw the early one's discount.
        $this->assertSame([], $early->seen[0]->adjustments);
        $this->assertSame($subtotal - 999, $late->seen[0]->total);
        $coupon = -intdiv($subtotal - 999, 10);
        $this->assertSame(['rule#3', 'coupon#SPRING'], array_column($state['adjustments'], 'source'));
        $this->assertSame($subtotal - 999 + $coupon, $state['total']);
        $this->assertSame('-9,99'.self::NBSP.'€', $state['adjustments'][0]['amount_formatted']);
        $this->assertSame($line, $state['adjustments'][0]['line']);
        $this->assertSame(['rule#3'], array_column($state['lines'][0]['adjustments'], 'source'));
        $this->assertSame(9998 - 999, $state['lines'][0]['line_total']);

        $this->assertSame(10000, $state['tenders'][0]['amount']);
        $this->assertSame('100,00'.self::NBSP.'€', $state['tenders'][0]['amount_formatted']);
        $this->assertSame($state['total'] - 10000, $state['payable']);
        $this->assertTrue($result->hasBenefit('shipping.paid_by_store'));
        $this->assertFalse($result->hasBenefit('shipping.express'));
        $this->assertSame(100000 - $state['total'], $state['hints'][0]['missing']);
        $this->assertNotNull($state['hints'][0]['missing_formatted']);
        $this->assertSame([], $state['warnings']);
    }

    #[Test]
    public function tenders_never_take_more_than_the_total(): void
    {
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1]]);
        $tenders = $this->app->make(TenderSources::class);
        $tenders->register(new FakeSources(static fn (): array => [new Tender('wallet', 'Balance', 3000)]));
        $tenders->register(new FakeSources(static fn (): array => [new Tender('certificate', 'Certificate', 5000)]));

        $state = $this->state($cart)->toArray();

        $this->assertSame([3000, 1999], array_column($state['tenders'], 'amount'));
        $this->assertSame(0, $state['payable']);
        $this->assertSame(['tenders.capped'], self::codes($state));
    }

    #[Test]
    public function a_declined_rule_is_left_out_and_a_line_too_deep_goes_to_the_root(): void
    {
        $cart = $this->cart([
            ['type' => 'test.product', 'ref' => 1, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 2, 'parent' => 'set'],
        ]);
        $child = CartItem::query()->whereNotNull('parent_id')->value('id');
        $cart->forceFill(['declined_rules' => ['rule#1']])->save();

        $this->app->make(LineSources::class)->register(new FakeSources(static fn (): array => [
            new GeneratedLine('rule#1', 'test.product', 3),
            new GeneratedLine('rule#2', 'test.product', 3, parentId: $child),
        ]));

        $state = $this->state($cart)->toArray();

        $this->assertCount(2, $state['lines']);
        $this->assertSame('rule#2:0', $state['lines'][1]['id']);
        $this->assertSame([], $state['lines'][0]['children'][0]['children']);
    }

    #[Test]
    public function a_gift_at_zero_is_refused_by_its_constructor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GeneratedLine('rule#1', 'test.product', 3, price: 0);
    }

    #[Test]
    public function a_failing_source_is_skipped_with_a_warning_but_never_for_an_order(): void
    {
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1]]);
        $this->app->make(AdjustmentSources::class)->register(new FakeSources(static fn (): array => throw new RuntimeException('secret detail')));

        $state = $this->state($cart)->toArray();

        $this->assertSame(4999, $state['total']);
        $this->assertSame(['totals.source_failed'], self::codes($state));
        $this->assertStringNotContainsString('secret', json_encode($state, JSON_THROW_ON_ERROR));

        $this->expectException(RuntimeException::class);
        $this->state($cart, purpose: Purpose::Order);
    }

    #[Test]
    public function an_empty_state_has_the_same_shape_with_zeros(): void
    {
        $state = $this->app->make(Calculator::class)->empty('en')->toArray();

        $this->assertSame([], $state['lines']);
        $this->assertSame(0, $state['units']);
        $this->assertSame('0,00'.self::NBSP.'€', $state['subtotal_formatted']);
        $this->assertSame('0,00'.self::NBSP.'€', $state['payable_formatted']);
        $this->assertSame([], $state['warnings']);
    }

    #[Test]
    public function every_amount_has_its_string_and_a_null_amount_a_null_one(): void
    {
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1], ['type' => 'test.product', 'ref' => 2]]);
        unset($this->products->lines[2]);

        $state = $this->state($cart)->toArray();
        $amounts = ['price', 'price_at_add', 'line_total'];

        foreach ($state['lines'] as $line) {
            foreach ($amounts as $amount) {
                $this->assertArrayHasKey($amount.'_formatted', $line);
                $this->assertSame($line[$amount] === null, $line[$amount.'_formatted'] === null, $amount);
            }
        }

        foreach (['subtotal', 'total', 'payable'] as $amount) {
            $this->assertIsString($state[$amount.'_formatted']);
        }
    }
}
