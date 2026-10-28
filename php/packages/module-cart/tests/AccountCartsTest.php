<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use Illuminate\Cookie\CookieJar;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Cookie;
use WebxUi\Admin\Visitors\Events\VisitorDeleting;
use WebxUi\Admin\Visitors\Events\VisitorSignedIn;
use WebxUi\Admin\Visitors\Events\VisitorSignedOut;
use WebxUi\Admin\Visitors\NullVisitors;
use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Cart\CartCookie;
use WebxUi\Cart\CartHandle;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Cart\Tests\Fakes\FakeVisitors;

/** The guest's cart and the visitor's account (§13.3 of the visitors spec). */
final class AccountCartsTest extends TestCase
{
    private const int VISITOR = 7;

    /** @param  list<array<string, mixed>>  $items */
    private function guestCart(array $items): Cart
    {
        $result = $this->app->make(CartService::class)->add(null, $items, 'pl');
        $this->withCartCookie((string) $result->token);

        return $result->cart;
    }

    /** @param  list<array<string, mixed>>  $items */
    private function accountCart(array $items, int $visitor = self::VISITOR): Cart
    {
        return $this->app->make(CartService::class)->add(null, $items, 'en', $visitor)->cart;
    }

    private function signIn(int $visitor = self::VISITOR): void
    {
        event(new VisitorSignedIn($visitor, 'password'));
    }

    /** @return array<string, int> "type#ref" of the top lines → qty */
    private function lines(Cart $cart): array
    {
        $lines = [];

        foreach (CartItem::query()->where('cart_id', $cart->id)->whereNull('parent_id')->get() as $item) {
            $lines[$item->type.'#'.$item->ref_id] = $item->qty;
        }

        return $lines;
    }

    private function queuedCookie(): ?Cookie
    {
        return $this->app->make(CookieJar::class)->queued($this->app->make(CartCookie::class)->name());
    }

    private function signedInAs(int $visitor): void
    {
        $this->app->instance(Visitors::class, new FakeVisitors(new Visitor($visitor, 'visitor@example.com', true)));
        $this->app->make(CartHandle::class)->reset();
    }

    #[Test]
    public function the_guests_lines_join_the_accounts_cart_and_the_same_line_adds_up(): void
    {
        $account = $this->accountCart([
            ['type' => 'test.product', 'ref' => 1, 'qty' => 2],
            ['type' => 'test.product', 'ref' => 3],
        ]);
        $guest = $this->guestCart([
            ['type' => 'test.product', 'ref' => 1, 'qty' => 3],
            ['type' => 'test.product', 'ref' => 2],
        ]);

        $this->signIn();

        $this->assertNull(Cart::query()->find($guest->id));
        $this->assertSame(['test.product#1' => 5, 'test.product#3' => 1, 'test.product#2' => 1], $this->lines($account));
        $this->assertSame(1, Cart::query()->count());

        $account->refresh();
        $this->assertSame(self::VISITOR, $account->user_id);
        // The guest's language is the one the visitor is on now.
        $this->assertSame('pl', $account->locale);

        // The cookie switches to the account's cart, with a token of its own.
        $cookie = $this->queuedCookie();
        $this->assertNotNull($cookie);
        $this->assertSame(CartCookie::hash((string) $cookie->getValue()), $account->token);
    }

    #[Test]
    public function without_an_account_cart_the_guests_becomes_it(): void
    {
        $guest = $this->guestCart([['type' => 'test.product', 'ref' => 1, 'qty' => 2]]);
        $oldToken = $guest->token;

        $this->signIn();

        $guest->refresh();
        $this->assertSame(self::VISITOR, $guest->user_id);
        $this->assertSame(['test.product#1' => 2], $this->lines($guest));
        $this->assertNotSame($oldToken, $guest->token);
        $this->assertSame(CartCookie::hash((string) $this->queuedCookie()?->getValue()), $guest->token);
    }

    #[Test]
    public function without_a_guest_cart_the_account_cart_stays_as_it_is(): void
    {
        $account = $this->accountCart([['type' => 'test.product', 'ref' => 1]]);
        $token = $account->token;

        $this->signIn();

        $account->refresh();
        $this->assertSame($token, $account->token);
        $this->assertSame(['test.product#1' => 1], $this->lines($account));
        $this->assertNull($this->queuedCookie());
    }

    #[Test]
    public function an_added_up_line_stops_at_the_limit_of_the_cart_and_of_its_provider(): void
    {
        $this->app['config']->set('webx-cart.qty_max', 10);
        $this->products->lines[2] = self::product('Item B', 15000, maxQty: 4);

        $account = $this->accountCart([
            ['type' => 'test.product', 'ref' => 1, 'qty' => 7],
            ['type' => 'test.product', 'ref' => 2, 'qty' => 3],
        ]);
        $this->guestCart([
            ['type' => 'test.product', 'ref' => 1, 'qty' => 6],
            ['type' => 'test.product', 'ref' => 2, 'qty' => 3],
        ]);

        $this->signIn();

        $this->assertSame(['test.product#1' => 10, 'test.product#2' => 4], $this->lines($account));
    }

    #[Test]
    public function a_bundle_comes_over_whole_and_never_merges(): void
    {
        $account = $this->accountCart([
            ['type' => 'test.product', 'ref' => 1],
            ['type' => 'test.product', 'ref' => 3, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 2, 'parent' => 'set'],
        ]);
        $this->guestCart([
            ['type' => 'test.product', 'ref' => 1, 'key' => 'set', 'qty' => 2],
            ['type' => 'test.product', 'ref' => 2, 'parent' => 'set'],
            ['type' => 'test.product', 'ref' => 3],
        ]);

        $this->signIn();

        $items = CartItem::query()->where('cart_id', $account->id)->orderBy('id')->get();
        $this->assertCount(6, $items);

        // Neither a guest's parent onto the single line, nor a single line onto a bundle's parent.
        $parents = $items->whereNull('parent_id')->where('ref_id', 1)->pluck('qty')->sort()->values()->all();
        $this->assertSame([1, 2], $parents);
        $this->assertCount(2, $items->whereNull('parent_id')->where('ref_id', 3));

        // The guest's child still points at the guest's parent, now in the account's cart.
        $guestParent = $items->whereNull('parent_id')->where('ref_id', 1)->firstWhere('qty', 2);
        $child = $items->firstWhere('parent_id', $guestParent?->id);
        $this->assertNotNull($child);
        $this->assertSame(2, $child->ref_id);
    }

    #[Test]
    public function the_declined_rules_of_both_carts_are_kept(): void
    {
        $account = $this->accountCart([['type' => 'test.product', 'ref' => 1]]);
        $account->update(['declined_rules' => ['rule#1']]);
        $guest = $this->guestCart([['type' => 'test.product', 'ref' => 2]]);
        $guest->update(['declined_rules' => ['rule#2', 'rule#1']]);

        $this->signIn();

        $this->assertSame(['rule#1', 'rule#2'], $account->refresh()->declinedRules());
    }

    #[Test]
    public function the_signed_in_visitor_opens_the_accounts_cart_and_a_guest_never_does(): void
    {
        $account = $this->accountCart([['type' => 'test.product', 'ref' => 1, 'qty' => 2]]);
        $this->accountCart([['type' => 'test.product', 'ref' => 3]], visitor: 8);

        $this->signedInAs(self::VISITOR);
        $this->assertSame($account->id, cart()->cart()?->id);
        $this->assertSame(2, cart()->count());
        $this->assertTrue(cart()->has('test.product', 1));
        $this->assertSame(4999 * 2, cart()->state()->subtotal);

        // A guest holding a cookie of an account's cart opens nothing.
        $this->app->instance(Visitors::class, new FakeVisitors(null));
        $this->app->make(CartHandle::class)->reset();
        $token = CartCookie::generate();
        $account->update(['token' => CartCookie::hash($token)]);
        $this->withCartCookie($token);
        $this->assertNull(cart()->cart());
        $this->assertSame(0, cart()->count());
    }

    #[Test]
    public function the_api_of_a_signed_in_visitor_writes_to_the_accounts_cart(): void
    {
        $this->app->instance(Visitors::class, new FakeVisitors(new Visitor(self::VISITOR, 'visitor@example.com', true)));

        $this->postJson('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => 1]]])->assertOk();
        $this->defaultCookies = [];
        // Another device, no cookie: the same cart, found by the account.
        $this->postJson('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => 1, 'qty' => 2]]])
            ->assertOk()
            ->assertJsonPath('units', 3);

        $cart = Cart::query()->sole();
        $this->assertSame(self::VISITOR, $cart->user_id);
        $this->defaultCookies = [];
        $this->getJson('/api/cart')->assertOk()->assertJsonPath('units', 3);
    }

    #[Test]
    public function signing_out_takes_the_cookie_and_the_account_cart_waits(): void
    {
        $account = $this->accountCart([['type' => 'test.product', 'ref' => 1]]);

        event(new VisitorSignedOut(self::VISITOR));

        $cookie = $this->queuedCookie();
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isCleared());
        $this->assertNotNull(Cart::query()->find($account->id));
    }

    #[Test]
    public function deleting_the_account_deletes_its_cart_and_nobody_elses(): void
    {
        $account = $this->accountCart([['type' => 'test.product', 'ref' => 1]]);
        $other = $this->accountCart([['type' => 'test.product', 'ref' => 2]], visitor: 8);
        $guest = $this->guestCart([['type' => 'test.product', 'ref' => 3]]);

        event(new VisitorDeleting(self::VISITOR));

        $this->assertNull(Cart::query()->find($account->id));
        $this->assertSame(0, CartItem::query()->where('cart_id', $account->id)->count());
        $this->assertNotNull(Cart::query()->find($other->id));
        $this->assertNotNull(Cart::query()->find($guest->id));
    }

    #[Test]
    public function with_the_panel_but_no_visitors_module_the_cookie_alone_opens_the_cart(): void
    {
        $this->app->singleton(Visitors::class, NullVisitors::class);
        $guest = $this->guestCart([['type' => 'test.product', 'ref' => 1, 'qty' => 2]]);
        $this->app->make(CartHandle::class)->reset();

        $this->assertSame($guest->id, cart()->cart()?->id);
        $this->assertSame(2, cart()->count());
        $this->assertNull($guest->refresh()->user_id);
    }

    #[Test]
    public function the_cart_listens_to_the_events_of_the_panels_seam(): void
    {
        $this->assertTrue(Event::hasListeners(VisitorSignedIn::class));
        $this->assertTrue(Event::hasListeners(VisitorSignedOut::class));
        $this->assertTrue(Event::hasListeners(VisitorDeleting::class));
    }
}
