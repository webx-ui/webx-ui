<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\CartCookie;
use WebxUi\Cart\CartHandle;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Facades\Cart as CartFacade;
use WebxUi\Cart\Models\Cart;

final class HelperTest extends TestCase
{
    /** @param  list<array<string, mixed>>  $items */
    private function visitorWith(array $items): string
    {
        $token = $this->app->make(CartService::class)->add(null, $items, 'en')->token;
        $this->assertNotNull($token);
        $this->withCartCookie($token);

        return $token;
    }

    /** @return int the queries a call ran */
    private function queries(callable $call): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $call();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    #[Test]
    public function without_a_cookie_the_counter_costs_no_query(): void
    {
        $this->assertSame(0, $this->queries(function (): void {
            $this->assertSame(0, cart()->count());
            $this->assertTrue(cart()->isEmpty());
            $this->assertFalse(cart()->has('test.product', 1));
        }));
    }

    #[Test]
    public function with_a_cookie_the_counter_costs_one_query_and_agrees_with_the_full_count(): void
    {
        $this->visitorWith([
            ['type' => 'test.product', 'ref' => 1, 'qty' => 2, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 2, 'qty' => 3, 'parent' => 'set'],
            ['type' => 'test.product', 'ref' => 3],
        ]);

        $this->assertSame(1, $this->queries(function (): void {
            $this->assertSame(9, cart()->count());
            $this->assertFalse(cart()->isEmpty());
            $this->assertTrue(cart()->has('test.product', 2));
            $this->assertFalse(cart()->has('test.product', 4));
        }));

        $this->assertSame(cart()->count(), cart()->state()->units());
        $this->assertSame(9, CartFacade::count());
    }

    #[Test]
    public function a_write_drops_what_the_request_remembered(): void
    {
        $this->visitorWith([['type' => 'test.product', 'ref' => 1]]);
        $this->assertSame(1, cart()->count());

        $cart = cart()->cart();
        $this->assertNotNull($cart);
        $this->app->make(CartService::class)->add($cart, [['type' => 'test.product', 'ref' => 1, 'qty' => 2]], 'en');

        $this->assertSame(3, cart()->count());
        $this->assertSame(3, cart()->state()->units());
    }

    #[Test]
    public function a_cookie_whose_cart_is_gone_opens_nothing(): void
    {
        $this->visitorWith([['type' => 'test.product', 'ref' => 1]]);
        Cart::query()->delete();
        $this->app->make(CartHandle::class)->forget();

        $this->assertSame(0, cart()->count());
        $this->assertTrue(cart()->state()->isEmpty());
        $this->assertNull(cart()->cart());

        // Nor does a cookie that is not a token at all, and it costs no query.
        $this->withCartCookie('not a token');
        $this->assertSame(0, $this->queries(fn () => cart()->count()));
    }

    #[Test]
    public function the_state_is_counted_once_a_request_in_the_language_of_the_page(): void
    {
        $this->products->lines[7] = self::product('Item D', 9000, names: ['en' => 'Item D', 'pl' => 'Produkt D']);
        $this->visitorWith([['type' => 'test.product', 'ref' => 7]]);
        unset($this->products->lines[7]);

        $this->app['request']->headers->set(CartHandle::LOCALE_HEADER, 'pl');
        $this->products->calls = [];

        $first = cart()->state();
        $this->assertSame($first, cart()->state());
        $this->assertCount(1, $this->products->calls);
        $this->assertSame('pl', $first->locale);
        $this->assertSame('Produkt D', $first->toArray()['lines'][0]['name']);

        // A language the site does not have is not taken from the header.
        $this->app['request']->headers->set(CartHandle::LOCALE_HEADER, 'xx');
        $this->assertSame('en', cart()->locale());
    }

    #[Test]
    public function the_cookie_is_http_only_lax_and_lives_the_configured_days(): void
    {
        $cookie = $this->app->make(CartCookie::class)->make(CartCookie::generate());

        $this->assertSame('webx_cart', $cookie->getName());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());
        $this->assertEqualsWithDelta(time() + 60 * 86400, $cookie->getExpiresTime(), 5);
    }
}
