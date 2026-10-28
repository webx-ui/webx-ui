<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Cart\Tests\Fakes\FakeSources;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Cart\Totals\AdjustmentSources;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\GeneratedLine;
use WebxUi\Cart\Totals\LineSources;
use WebxUi\Cart\Totals\Warning;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;

/**
 * The carts for an agent (§10): four reading tools, one resource, nothing that writes and nothing
 * of the token.
 */
final class McpTest extends McpTestCase
{
    #[Test]
    public function the_module_offers_four_reading_tools_behind_its_permission_and_the_catalogue(): void
    {
        $registry = $this->app->make(ToolRegistry::class);
        $tools = $registry->toolsOf('cart');

        $this->assertSame(['cart_list', 'cart_get', 'cart_summary', 'cart_quote'], array_map(static fn ($tool): string => $tool->fullName(), $tools));

        foreach ($tools as $tool) {
            $this->assertFalse($tool->tool->mutating, $tool->fullName().' writes.');
            $this->assertSame(['cart.view'], $tool->permissions());
            $this->assertSame('cart:read', $tool->scope());
        }

        $this->assertContains('cart://catalog', array_map(static fn (McpResource $resource): string => $resource->uri, $registry->resources()));
    }

    #[Test]
    public function the_list_pages_the_carts_with_their_sums_at_the_prices_of_adding(): void
    {
        $bundle = $this->cartOf([
            ['type' => 'test.product', 'ref' => 1, 'qty' => 2, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 2, 'qty' => 3, 'parent' => 'set'],
        ]);
        $single = $this->cartOf([['type' => 'test.product', 'ref' => 3]]);
        $bundle->forceFill(['updated_at' => Carbon::now()->subDays(10)])->save();

        // Not recounted: the price moved, the list still sums what was paid attention to then.
        $this->products->lines[1] = self::product('Item A', 1);

        $list = $this->content($this->agent('cart_list'));

        $this->assertSame(2, $list['total']);
        $this->assertSame([$single->id, $bundle->id], array_column($list['carts'], 'id'));

        $row = $list['carts'][1];
        $this->assertSame(2, $row['lines']);
        // The child counts its qty per unit of the parent: 2 + 3 × 2.
        $this->assertSame(8, $row['units']);
        $this->assertSame(4999 * 2 + 15000 * 6, $row['total_at_add']);
        $this->assertSame("999,98\u{00A0}€", $row['total_at_add_formatted']);
        $this->assertSame('EUR', $row['currency']);
        $this->assertSame(['id', 'locale', 'currency', 'lines', 'units', 'total_at_add', 'total_at_add_formatted', 'created_at', 'updated_at'], array_keys($row));

        $this->assertSame([$bundle->id], array_column($this->content($this->agent('cart_list', ['contains' => ['type' => 'test.product', 'ref' => 2]]))['carts'], 'id'));
        $this->assertSame([$bundle->id], array_column($this->content($this->agent('cart_list', ['min_units' => 2]))['carts'], 'id'));
        $this->assertSame([$single->id], array_column($this->content($this->agent('cart_list', ['updated_since' => Carbon::now()->subDay()->toIso8601String()]))['carts'], 'id'));
        $this->assertSame([$bundle->id], array_column($this->content($this->agent('cart_list', ['updated_before' => Carbon::now()->subDay()->toIso8601String()]))['carts'], 'id'));
        $this->assertSame([$bundle->id, $single->id], array_column($this->content($this->agent('cart_list', ['order' => 'asc']))['carts'], 'id'));
    }

    #[Test]
    public function the_list_refuses_what_it_cannot_read_in_words(): void
    {
        $this->agent('cart_list', ['contains' => ['type' => 'shop.thing', 'ref' => 1]])
            ->assertHasErrors(['There is no purchasable type "shop.thing". Known: test.product, test.configuration.']);
        $this->agent('cart_list', ['updated_since' => 'yesterday-ish'])->assertHasErrors(['`updated_since` must be a moment']);
        $this->agent('cart_list', ['min_units' => 0])->assertHasErrors(['`min_units`']);
        $this->agent('cart_list', ['token' => 'abc'])->assertHasErrors(['cart_list has no argument [token]']);
    }

    #[Test]
    public function get_counts_the_cart_as_the_visitor_sees_it_now(): void
    {
        $cart = $this->cartOf([
            ['type' => 'test.product', 'ref' => 1, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 2, 'parent' => 'set'],
        ]);
        $this->products->lines[1] = self::product('Item A', 3999, names: ['en' => 'Item A', 'pl' => 'Rzecz A']);
        $this->app->make(AdjustmentSources::class)->register(new FakeSources(static fn (): array => [
            new Adjustment('rule#7', 'Spring', -500, meta: ['rule' => 7]),
        ]));

        $state = $this->content($this->agent('cart_get', ['cart' => $cart->id]));

        $this->assertSame($cart->id, $state['id']);
        $this->assertSame(3999 + 15000, $state['subtotal']);
        $this->assertSame(3999 + 15000 - 500, $state['total']);
        $this->assertSame('down', $state['lines'][0]['price_change']);
        $this->assertSame(2, $state['lines'][0]['children'][0]['ref']);
        // What the API leaves out, an agent explaining a discount gets.
        $this->assertSame(['rule' => 7], $state['adjustments'][0]['meta']);
        $this->assertSame([], $state['declined_rules']);
        $this->assertNotNull($state['created_at']);

        $this->assertSame('pl', $this->content($this->agent('cart_get', ['cart' => $cart->id, 'locale' => 'pl']))['locale']);
        $this->agent('cart_get', ['cart' => $cart->id, 'locale' => 'xx'])->assertHasErrors(['The site has no language "xx". It has: en, pl.']);
        $this->agent('cart_get', ['cart' => 999])->assertHasErrors(['Cart 999 does not exist.']);
    }

    #[Test]
    public function no_answer_carries_the_token(): void
    {
        $result = $this->app->make(CartService::class)->add(null, [['type' => 'test.product', 'ref' => 1]], 'en');
        $token = (string) $result->token;
        $hash = (string) Cart::query()->value('token');

        foreach ([
            ['cart_list', []],
            ['cart_get', ['cart' => $result->cart->id]],
            ['cart_summary', []],
        ] as [$tool, $arguments]) {
            $json = json_encode($this->content($this->agent($tool, $arguments)), JSON_THROW_ON_ERROR);

            $this->assertStringNotContainsString($token, $json, $tool);
            $this->assertStringNotContainsString($hash, $json, $tool);
            $this->assertStringNotContainsString('token', $json, $tool);
        }
    }

    #[Test]
    public function the_summary_counts_active_and_abandoned_carts_and_the_records_in_most(): void
    {
        $this->cartOf([['type' => 'test.product', 'ref' => 1], ['type' => 'test.product', 'ref' => 2]]);
        $this->cartOf([['type' => 'test.product', 'ref' => 1]])->forceFill(['updated_at' => Carbon::now()->subDays(3)])->save();
        $this->cartOf([['type' => 'test.product', 'ref' => 1]])->forceFill(['updated_at' => Carbon::now()->subDays(20)])->save();
        $this->cartOf([['type' => 'test.product', 'ref' => 3]])->forceFill(['updated_at' => Carbon::now()->subDays(40)])->save();
        // An emptied cart is not abandoned: there is nothing in it to come back for.
        Cart::query()->create(['token' => str_repeat('e', 64), 'locale' => 'en', 'currency' => 'EUR'])
            ->forceFill(['updated_at' => Carbon::now()->subDays(40)])->save();
        unset($this->products->lines[3]);

        $summary = $this->content($this->agent('cart_summary', ['top' => 2]));

        $this->assertSame(5, $summary['carts']);
        $this->assertSame(['day' => 1, 'week' => 2, 'month' => 3], $summary['active']);
        $this->assertSame(['days' => 7, 'carts' => 2], $summary['abandoned']);
        $this->assertSame([
            ['type' => 'test.product', 'ref' => 1, 'name' => 'Item A', 'gone' => false, 'carts' => 3],
            ['type' => 'test.product', 'ref' => 2, 'name' => 'Item B', 'gone' => false, 'carts' => 1],
        ], $summary['top']);

        $this->assertSame(1, $this->content($this->agent('cart_summary', ['days' => 30]))['abandoned']['carts']);
        $gone = $this->content($this->agent('cart_summary', ['top' => 3]))['top'][2];
        $this->assertSame(['ref' => 3, 'name' => null, 'gone' => true], ['ref' => $gone['ref'], 'name' => $gone['name'], 'gone' => $gone['gone']]);
        $this->agent('cart_summary', ['top' => 51])->assertHasErrors(['`top` must be a whole number from 1 to 50.']);
    }

    #[Test]
    public function a_quote_counts_what_the_visitor_would_get_and_keeps_nothing(): void
    {
        $this->app->make(LineSources::class)->register(new FakeSources(static fn (): array => [
            new GeneratedLine('rule#1', 'test.product', 3, price: 1, label: 'Gift'),
        ]));

        $quote = $this->content($this->agent('cart_quote', ['items' => [
            ['type' => 'test.product', 'ref' => 1, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 2, 'qty' => 2, 'parent' => 'set'],
        ]]));

        $this->assertTrue($quote['accepted']);
        $this->assertSame([], $quote['errors']);
        $this->assertSame(4999 + 30000 + 1, $quote['state']['total']);
        $this->assertSame('rule#1:0', $quote['state']['lines'][1]['id']);
        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());

        $declined = $this->content($this->agent('cart_quote', ['items' => [['type' => 'test.product', 'ref' => 1]], 'declined' => ['rule#1']]));
        $this->assertSame(4999, $declined['state']['total']);
        $this->assertCount(1, $declined['state']['lines']);
        $this->assertSame(0, Cart::query()->count());
    }

    #[Test]
    public function a_quote_answers_the_refusals_the_visitor_would_get(): void
    {
        $this->products->lines[2] = self::product('Item B', 15000, available: false);

        $quote = $this->content($this->agent('cart_quote', ['items' => [
            ['type' => 'test.product', 'ref' => 1],
            ['type' => 'test.product', 'ref' => 2],
            ['type' => 'shop.thing', 'ref' => 1],
        ], 'locale' => 'pl']));

        $this->assertFalse($quote['accepted']);
        $this->assertNull($quote['state']);
        $this->assertSame(['items.2.type'], array_keys($quote['errors']));

        $quote = $this->content($this->agent('cart_quote', ['items' => [['type' => 'test.product', 'ref' => 2]]]));
        $this->assertSame(['items.0.ref' => ['This item cannot be bought now.']], $quote['errors']);
        $this->assertSame(0, Cart::query()->count());

        $this->agent('cart_quote', ['items' => 'one'])->assertHasErrors(['`items` must be a list of lines']);
        $this->agent('cart_quote', ['items' => [], 'declined' => [12]])->assertHasErrors(['`declined` must be a list of sources']);
    }

    #[Test]
    public function the_catalogue_is_read_from_the_registries_and_the_constants(): void
    {
        $this->app->make(AdjustmentSources::class)->register(new FakeSources(static fn (): array => []), 20);

        $catalog = ($this->resource('cart://catalog')->handler)();

        $this->assertSame([
            ['type' => 'test.product', 'takes_options' => false, 'price_by_options' => false],
            ['type' => 'test.configuration', 'takes_options' => true, 'price_by_options' => true],
        ], $catalog['types']);
        $this->assertSame([['source' => FakeSources::class, 'priority' => 20]], $catalog['chain']['adjustments']);
        $this->assertSame([], $catalog['chain']['tenders']);
        $this->assertSame('EUR', $catalog['settings']['currency']);
        $this->assertSame(20, $catalog['settings']['batch_max']);
        $this->assertContains('payable_formatted', $catalog['state_fields']);
        $this->assertSame(Warning::CODES, array_column($catalog['warnings'], 'code'));
        $this->assertSame(':name is no longer available. You can remove it.', $catalog['warnings'][0]['phrase']);
    }

    #[Test]
    public function the_line_fields_of_the_catalogue_are_the_fields_of_a_line(): void
    {
        $cart = $this->cartOf([['type' => 'test.product', 'ref' => 1]]);

        $state = $this->content($this->agent('cart_get', ['cart' => $cart->id]));

        $this->assertSame(CartState::LINE_FIELDS, array_keys($state['lines'][0]));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function cartOf(array $items): Cart
    {
        return $this->app->make(CartService::class)->add(null, $items, 'en')->cart;
    }

    private function resource(string $uri): McpResource
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                return $resource;
            }
        }

        $this->fail("No resource [{$uri}].");
    }
}
