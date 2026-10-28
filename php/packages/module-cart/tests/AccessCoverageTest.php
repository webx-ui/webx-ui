<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Testing\AssertsPermissions;

/**
 * The cart has no screen of the panel; what it serves on the site is the visitor's own cart.
 * Every route of the module is named here as open with the reason — a new one that is not fails
 * until somebody says why it may be — and none of them stands behind the panel's guard or can
 * reach anybody else's cart (`ApiTest`). Its agent tools are behind `cart.view`
 * (WEBX_UI_PERMISSIONS.md §15).
 */
final class AccessCoverageTest extends McpTestCase
{
    use AssertsPermissions;

    private const OPEN = [
        'GET api/cart' => 'the visitor\'s cart: the cookie\'s token opens only its own',
        'DELETE api/cart' => 'the visitor\'s cart: the cookie\'s token opens only its own',
        'POST api/cart/items' => 'the visitor\'s cart: the cookie\'s token opens only its own',
        'PATCH api/cart/items/{id}' => 'the visitor\'s cart: a line of another cart is 404',
        'DELETE api/cart/items/{id}' => 'the visitor\'s cart: a line of another cart is 404',
    ];

    #[Test]
    public function every_route_is_the_visitors_own_cart_and_says_so(): void
    {
        $found = [];
        $problems = [];

        foreach ($this->app->make('router')->getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            if (! str_starts_with(ltrim($route->getActionName(), '\\'), 'WebxUi\\Cart\\')) {
                continue;
            }

            $key = array_values(array_diff($route->methods(), ['HEAD']))[0].' '.$route->uri();
            $found[] = $key;
            $middleware = array_filter($route->gatherMiddleware(), is_string(...));

            if (! array_key_exists($key, self::OPEN)) {
                $problems[] = "{$key}: not named as open, and it asks for no permission.";
            }

            if (! in_array('web', $middleware, true)) {
                $problems[] = "{$key}: outside the web group — no session, no CSRF.";
            }

            if ($route->methods() !== ['GET', 'HEAD'] && ! in_array('throttle:webx-cart', $middleware, true)) {
                $problems[] = "{$key}: a write without the throttle.";
            }
        }

        foreach (array_keys(self::OPEN) as $key) {
            if (! in_array($key, $found, true)) {
                $problems[] = "{$key}: named as open, and there is no such route.";
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    #[Test]
    public function every_tool_asks_for_the_declared_permission(): void
    {
        $this->assertToolsNeedPermission('cart');
    }
}
