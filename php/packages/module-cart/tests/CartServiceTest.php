<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\AddResult;
use WebxUi\Cart\CartCookie;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;

final class CartServiceTest extends TestCase
{
    private function service(): CartService
    {
        return $this->app->make(CartService::class);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, list<string>>
     */
    private function refused(?Cart $cart, array $items, string $locale = 'en'): array
    {
        try {
            $this->service()->add($cart, $items, $locale);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('The batch went in.');
    }

    /** @param  list<array<string, mixed>>  $items */
    private function add(?Cart $cart, array $items, string $locale = 'en'): AddResult
    {
        return $this->service()->add($cart, $items, $locale);
    }

    #[Test]
    public function the_first_batch_makes_the_cart_with_a_token_whose_hash_is_stored(): void
    {
        $result = $this->add(null, [['type' => 'test.product', 'ref' => 1, 'qty' => 2]]);

        $this->assertNotNull($result->token);
        $this->assertSame(43, strlen($result->token));
        $this->assertSame(CartCookie::hash($result->token), $result->cart->token);
        $this->assertSame('EUR', $result->cart->currency);
        $this->assertSame('en', $result->cart->locale);

        $item = CartItem::query()->sole();
        $this->assertSame([$item->id], $result->added);
        $this->assertSame(2, $item->qty);
        $this->assertSame(4999, $item->price_at_add);
        $this->assertSame(['en' => 'Item A'], $item->name_at_add);

        // A second batch goes into the same cart and hands out no new token.
        $again = $this->add($result->cart, [['type' => 'test.product', 'ref' => 2]]);
        $this->assertNull($again->token);
        $this->assertSame(1, Cart::query()->count());
    }

    #[Test]
    public function a_batch_with_one_bad_line_writes_nothing_not_even_the_cart(): void
    {
        $errors = $this->refused(null, [
            ['type' => 'test.product', 'ref' => 1],
            ['type' => 'test.product', 'ref' => 404],
        ]);

        $this->assertArrayHasKey('items.1.ref', $errors);
        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());
    }

    #[Test]
    public function an_unknown_type_is_refused_with_the_types_there_are(): void
    {
        $errors = $this->refused(null, [['type' => 'blog.article', 'ref' => 1]]);

        $this->assertStringContainsString('test.product, test.configuration', $errors['items.0.type'][0]);
    }

    #[Test]
    public function a_bundle_goes_in_whole_with_children_under_their_parent(): void
    {
        $result = $this->add(null, [
            ['type' => 'test.product', 'ref' => 1, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 2, 'qty' => 2, 'parent' => 'set'],
            ['type' => 'test.product', 'ref' => 3, 'parent' => 'set'],
        ]);

        $parent = CartItem::query()->whereNull('parent_id')->sole();
        $children = CartItem::query()->where('parent_id', $parent->id)->orderBy('id')->get();

        $this->assertSame(1, $parent->ref_id);
        $this->assertSame([2, 3], $children->pluck('ref_id')->all());
        $this->assertSame([2, 1], $children->pluck('qty')->all());
        $this->assertCount(3, $result->added);
    }

    #[Test]
    public function a_parent_outside_the_batch_or_with_a_parent_of_its_own_is_refused(): void
    {
        $outside = $this->refused(null, [
            ['type' => 'test.product', 'ref' => 1, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 2, 'parent' => 'elsewhere'],
        ]);
        $this->assertArrayHasKey('items.1.parent', $outside);

        $grandchild = $this->refused(null, [
            ['type' => 'test.product', 'ref' => 1, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 2, 'key' => 'child', 'parent' => 'set'],
            ['type' => 'test.product', 'ref' => 3, 'parent' => 'child'],
        ]);
        $this->assertArrayHasKey('items.2.parent', $grandchild);

        $this->assertSame(0, CartItem::query()->count());
    }

    #[Test]
    public function the_same_line_adds_to_the_one_there_and_a_bundle_never_merges(): void
    {
        $cart = $this->add(null, [
            ['type' => 'test.configuration', 'ref' => 5, 'options' => ['size' => 2, 'colour' => 'white']],
        ])->cart;

        $this->add($cart, [
            ['type' => 'test.configuration', 'ref' => 5, 'qty' => 2, 'options' => ['colour' => 'white', 'size' => 2]],
            ['type' => 'test.product', 'ref' => 1],
            ['type' => 'test.product', 'ref' => 1],
        ]);

        $this->assertSame(2, CartItem::query()->count());
        $this->assertSame(3, CartItem::query()->where('type', 'test.configuration')->value('qty'));
        $plain = CartItem::query()->where('type', 'test.product')->sole();
        $this->assertSame(2, $plain->qty);

        // A bundle of the same product stays a bundle of its own, and so does its child.
        $this->add($cart, [
            ['type' => 'test.product', 'ref' => 1, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 1, 'parent' => 'set'],
        ]);

        $this->assertSame(3, CartItem::query()->where('type', 'test.product')->count());

        // A plain line adds to the plain line, not to the bundle's parent.
        $this->add($cart, [['type' => 'test.product', 'ref' => 1]]);
        $this->assertSame(3, $plain->refresh()->qty);
        $this->assertSame([1, 1], CartItem::query()->whereKeyNot($plain->id)->where('type', 'test.product')->pluck('qty')->all());
    }

    #[Test]
    public function removing_a_parent_keeps_its_children_with_their_qty_multiplied(): void
    {
        $cart = $this->add(null, [
            ['type' => 'test.product', 'ref' => 1, 'qty' => 3, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 2, 'qty' => 2, 'parent' => 'set'],
        ])->cart;

        $parent = CartItem::query()->whereNull('parent_id')->sole();
        $this->service()->remove($cart, $parent->id);

        $child = CartItem::query()->sole();
        $this->assertNull($child->parent_id);
        $this->assertSame(6, $child->qty);
    }

    #[Test]
    public function a_delete_past_the_application_leaves_the_children_without_a_parent(): void
    {
        $this->add(null, [
            ['type' => 'test.product', 'ref' => 1, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 2, 'parent' => 'set'],
        ]);

        CartItem::query()->whereNull('parent_id')->delete();

        $child = CartItem::query()->sole();
        $this->assertNull($child->parent_id);
        $this->assertSame(1, $child->qty);
    }

    #[Test]
    public function the_name_snapshot_keeps_every_language_the_provider_gave(): void
    {
        $this->products->lines[7] = self::product('Item D', 9000, names: ['en' => 'Item D', 'pl' => 'Produkt D']);

        $this->add(null, [
            ['type' => 'test.product', 'ref' => 7],
            ['type' => 'test.product', 'ref' => 2],
        ], 'pl');

        $this->assertSame(['en' => 'Item D', 'pl' => 'Produkt D'], CartItem::query()->where('ref_id', 7)->value('name_at_add'));
        $this->assertSame(['pl' => 'Item B'], CartItem::query()->where('ref_id', 2)->value('name_at_add'));
    }

    #[Test]
    public function what_cannot_be_bought_does_not_go_in(): void
    {
        $this->products->lines[4] = self::product('On request', 0);
        $this->products->lines[5] = self::product('Off sale', 1000, available: false);
        $this->products->lines[6] = self::product('Elsewhere', 1000, currency: 'USD');

        $errors = $this->refused(null, [
            ['type' => 'test.product', 'ref' => 4],
            ['type' => 'test.product', 'ref' => 5],
            ['type' => 'test.product', 'ref' => 6],
        ]);

        $this->assertSame(['items.0.ref', 'items.1.ref', 'items.2.ref'], array_keys($errors));
        $this->assertSame('This item is priced in another currency.', $errors['items.2.ref'][0]);
    }

    #[Test]
    public function the_qty_is_held_to_the_stock_and_the_limit(): void
    {
        $this->products->lines[8] = self::product('Few left', 1000, maxQty: 3);
        config()->set('webx-cart.qty_max', 10);

        $this->assertArrayHasKey('items.0.qty', $this->refused(null, [['type' => 'test.product', 'ref' => 8, 'qty' => 4]]));
        $this->assertArrayHasKey('items.0.qty', $this->refused(null, [['type' => 'test.product', 'ref' => 1, 'qty' => 11]]));
        $this->assertArrayHasKey('items.0.qty', $this->refused(null, [['type' => 'test.product', 'ref' => 1, 'qty' => 0]]));

        // A child counts per unit of its parent: 2 × 2 is over a stock of 3.
        $this->assertArrayHasKey('items.1.qty', $this->refused(null, [
            ['type' => 'test.product', 'ref' => 1, 'qty' => 2, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 8, 'qty' => 2, 'parent' => 'set'],
        ]));

        // Adding to a line already there counts what is there.
        $cart = $this->add(null, [['type' => 'test.product', 'ref' => 8, 'qty' => 2]])->cart;
        $this->assertArrayHasKey('items.0.qty', $this->refused($cart, [['type' => 'test.product', 'ref' => 8, 'qty' => 2]]));
    }

    #[Test]
    public function the_batch_and_the_cart_have_their_limits(): void
    {
        config()->set('webx-cart.batch_max', 2);
        config()->set('webx-cart.lines_max', 3);

        $this->assertArrayHasKey('items', $this->refused(null, array_fill(0, 3, ['type' => 'test.product', 'ref' => 1])));
        $this->assertArrayHasKey('items', $this->refused(null, []));

        $cart = $this->add(null, [['type' => 'test.product', 'ref' => 1], ['type' => 'test.product', 'ref' => 2]])->cart;
        $this->add($cart, [['type' => 'test.product', 'ref' => 3]]);

        // A fourth line is over; another unit of a line there is not.
        $this->products->lines[4] = self::product('Item E', 100);
        $this->assertArrayHasKey('items', $this->refused($cart, [['type' => 'test.product', 'ref' => 4]]));
        $this->add($cart, [['type' => 'test.product', 'ref' => 1]]);
        $this->assertSame(3, CartItem::query()->count());
    }

    #[Test]
    public function options_go_only_to_a_provider_that_checks_them(): void
    {
        $plain = $this->refused(null, [['type' => 'test.product', 'ref' => 1, 'options' => ['size' => 2]]]);
        $this->assertSame(['This item takes no options.'], $plain['items.0.options']);

        $checked = $this->refused(null, [['type' => 'test.configuration', 'ref' => 5, 'options' => ['size' => 7]]]);
        $this->assertSame(['The size is from 1 to 3.'], $checked['items.0.options']);

        $this->add(null, [['type' => 'test.configuration', 'ref' => 5, 'options' => ['size' => 3]]]);
        $item = CartItem::query()->sole();
        $this->assertSame(['size' => 3], $item->options);
        $this->assertSame(13000, $item->price_at_add);
    }

    #[Test]
    public function without_a_currency_the_cart_takes_no_writes(): void
    {
        config()->set('webx-cart.currency', null);

        $this->assertArrayHasKey('currency', $this->refused(null, [['type' => 'test.product', 'ref' => 1]]));
        $this->assertSame(0, Cart::query()->count());
    }

    #[Test]
    public function update_changes_qty_and_options_of_a_line_of_this_cart_only(): void
    {
        $cart = $this->add(null, [['type' => 'test.configuration', 'ref' => 5, 'options' => ['size' => 1]]])->cart;
        $other = $this->add(null, [['type' => 'test.product', 'ref' => 1]])->cart;
        $line = CartItem::query()->where('cart_id', $cart->id)->sole();

        $this->service()->update($cart, $line->id, 4, ['size' => 2], 'en');
        $line->refresh();
        $this->assertSame(4, $line->qty);
        $this->assertSame(['size' => 2], $line->options);

        try {
            $this->service()->update($cart, $line->id, null, ['size' => 9], 'en');
            $this->fail('Options the provider refuses went in.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('options', $e->errors());
        }

        $this->expectException(ModelNotFoundException::class);
        $foreign = CartItem::query()->where('cart_id', $other->id)->sole();
        $this->service()->update($cart, $foreign->id, 2, null, 'en');
    }

    #[Test]
    public function clear_takes_the_cart_and_its_lines(): void
    {
        $cart = $this->add(null, [['type' => 'test.product', 'ref' => 1, 'key' => 'set'], ['type' => 'test.product', 'ref' => 2, 'parent' => 'set']])->cart;

        $this->service()->clear($cart);

        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());
    }

    #[Test]
    public function empty_takes_the_lines_and_the_declined_rules_and_keeps_the_cart_and_its_token(): void
    {
        $result = $this->add(null, [['type' => 'test.product', 'ref' => 1, 'key' => 'set'], ['type' => 'test.product', 'ref' => 2, 'parent' => 'set'], ['type' => 'test.product', 'ref' => 3]]);
        $cart = $result->cart;
        $cart->forceFill(['declined_rules' => ['rule#1']])->save();

        $this->service()->empty($cart);

        $kept = Cart::query()->sole();
        $this->assertSame($cart->id, $kept->id);
        $this->assertSame(CartCookie::hash((string) $result->token), $kept->token);
        $this->assertNull($kept->declined_rules);
        $this->assertSame(0, CartItem::query()->count());

        // The same cart takes lines again, and no new token is handed out.
        $again = $this->add($kept, [['type' => 'test.product', 'ref' => 1]]);
        $this->assertNull($again->token);
        $this->assertSame(1, CartItem::query()->where('cart_id', $cart->id)->count());
    }

    #[Test]
    public function a_write_reads_the_lines_under_the_lock_not_from_before_it(): void
    {
        // The model of a request that began before an order emptied the cart: the line it would
        // add to is gone, and the batch lands as a line of its own rather than nowhere.
        $cart = $this->add(null, [['type' => 'test.product', 'ref' => 1]])->cart;
        $stale = Cart::query()->findOrFail($cart->id);
        $stale->load('items');

        $this->service()->empty($cart);
        $this->add($stale, [['type' => 'test.product', 'ref' => 1, 'qty' => 2]]);

        $this->assertSame(2, CartItem::query()->sole()->qty);

        // A line taken by an order meanwhile is as missing to update and remove as one that never was.
        $line = CartItem::query()->sole();
        $this->service()->empty($cart);

        foreach ([fn () => $this->service()->update($stale, $line->id, 3, null, 'en'), fn () => $this->service()->remove($stale, $line->id)] as $write) {
            try {
                $write();
                $this->fail('A write went to a line that is gone.');
            } catch (ModelNotFoundException) {
                $this->assertSame(0, CartItem::query()->count());
            }
        }
    }
}
