<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cart\AddResult;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Cart\Totals\AdjustmentSources;
use WebxUi\Cart\Totals\Benefit;
use WebxUi\Cart\Totals\BenefitSources;
use WebxUi\Cart\Totals\CartContext;
use WebxUi\Cart\Totals\Tender;
use WebxUi\Cart\Totals\TenderSources;
use WebxUi\Orders\ItemsEdit;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Orders;
use WebxUi\Orders\Tests\Fakes\FakeSources;

/**
 * The query budget of placing an order (§16, WEBX_UI_PERFORMANCE.md): the lines, adjustments and
 * tenders go in a statement a table, not one a row, and the chain is counted once — so an order
 * of four lines and one of forty cost the same.
 */
final class PerformanceTest extends PlaceTestCase
{
    use CountsQueries;

    #[Test]
    public function placing_costs_the_same_with_four_lines_and_with_forty(): void
    {
        for ($ref = 10; $ref < 50; $ref++) {
            $this->products->lines[$ref] = self::product("Item {$ref}", 1000 + $ref);
        }

        // A discount on every line, one of the cart, a tender and a benefit.
        $this->app->make(AdjustmentSources::class)->register(new FakeSources(static function (CartContext $cart): array {
            $adjustments = [new Adjustment('coupon#SPRING', 'Spring', -100)];

            foreach ($cart->lines as $line) {
                $adjustments[] = new Adjustment('rule#3', 'Less', -10, $line->key);
            }

            return $adjustments;
        }));
        $this->app->make(TenderSources::class)->register(new FakeSources(static fn (): array => [new Tender('wallet', 'Certificate', 500)]));
        $this->app->make(BenefitSources::class)->register(new FakeSources(static fn (): array => [new Benefit('shipping.paid_by_store', 'Free shipping', 'rule#4')]));

        $counts = [];

        foreach ([4, 40] as $lines) {
            $cart = $this->bundles($lines);

            // 26: the key, the cart's lock, the key again under it (a twin waited there), the lines;
            // the initial status; the order and its number; the roots, their ids, the children,
            // their ids; adjustments, tenders, benefits, milestones, the history; the cart emptied
            // (its lock again — the cart's own writes take it — children, roots); the order read
            // back for OrderPlaced (lines, adjustments, tenders, benefits, milestones, the status
            // and its group). The repeats are these by design, none of them a row's.
            $tally = $this->recordQueries(fn () => $this->place($cart, ['idempotencyKey' => "budget-{$lines}"]));
            $this->assertLessThanOrEqual(26, $tally->count(), "{$lines} lines".PHP_EOL.$tally->describe());
            $counts[$lines] = $tally->count();
        }

        $this->assertSame($counts[4], $counts[40]);
    }

    #[Test]
    public function an_edit_costs_the_same_with_four_lines_and_with_forty(): void
    {
        for ($ref = 10; $ref < 50; $ref++) {
            $this->products->lines[$ref] = self::product("Item {$ref}", 1000 + $ref);
        }

        // A discount on every line and one of the cart, as at placing.
        $this->app->make(AdjustmentSources::class)->register(new FakeSources(static function (CartContext $cart): array {
            $adjustments = [new Adjustment('coupon#SPRING', 'Spring', -100)];

            foreach ($cart->lines as $line) {
                $adjustments[] = new Adjustment('rule#3', 'Less', -10, $line->key);
            }

            return $adjustments;
        }));

        $counts = [];

        foreach ([4, 40] as $lines) {
            $order = $this->place($this->bundles($lines), ['idempotencyKey' => "edit-{$lines}"]);
            $first = OrderItem::query()->where('order_id', $order->id)->whereNull('parent_id')->orderBy('id')->firstOrFail();
            $edit = new ItemsEdit($order->items_revision, add: [['type' => 'test.product', 'ref' => 2]], qty: [$first->id => 2], source: 'api');

            // 25: the order, its lines, their discounts; the cart of the edit (the cart, its line, its
            // lines read for the count); the benefits for the warnings; an update a row changed — the
            // bundle edited is a parent and its child, each with its discount — never one a line of
            // the order; the lines added and their ids; the order; the edit; the history; the journal;
            // the cart gone; the order read back for OrderItemsChanged (lines, adjustments, tenders,
            // benefits, milestones, the status and its group).
            $tally = $this->recordQueries(fn () => $this->app->make(Orders::class)->editItems($order, $edit));
            $this->assertLessThanOrEqual(25, $tally->count(), "{$lines} lines".PHP_EOL.$tally->describe());
            $counts[$lines] = $tally->count();
        }

        $this->assertSame($counts[4], $counts[40]);
    }

    /** A cart of bundles: a parent and a child each, `$lines` lines in all. */
    private function bundles(int $lines): AddResult
    {
        $items = [];

        for ($i = 0; $i < $lines; $i += 2) {
            $items[] = ['type' => 'test.product', 'ref' => 10 + $i, 'key' => "set{$i}"];
            $items[] = ['type' => 'test.product', 'ref' => 11 + $i, 'parent' => "set{$i}"];
        }

        $batches = array_chunk($items, 20);
        $cart = $this->cart($batches[0]);

        foreach (array_slice($batches, 1) as $batch) {
            $this->app->make(CartService::class)->add($cart->cart, $batch, 'en');
        }

        return $cart;
    }
}
