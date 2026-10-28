<?php

declare(strict_types=1);

namespace WebxUi\Cart;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use WebxUi\Cart\Models\Cart;

/**
 * The visitor's token and the cart it opens.
 *
 * The token is 32 random bytes in base64url; the cookie holds it and `carts.token` its sha256, so
 * a leaked database opens nobody's cart. A cookie whose cart is gone opens nothing, and the next
 * write starts a new cart with a new token.
 */
final class CartCookie
{
    private const string FORMAT = '/^[A-Za-z0-9_-]{43}$/';

    public function __construct(
        private readonly Repository $config,
        private readonly CookieJar $cookies,
    ) {}

    public static function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function name(): string
    {
        $name = $this->config->get('webx-cart.cookie.name');

        return is_string($name) && $name !== '' ? $name : 'webx_cart';
    }

    /** The token the request carries, or null — read when asked, never from the request that made this. */
    public function token(?Request $request = null): ?string
    {
        $request ??= request();
        $token = $request->cookie($this->name());

        return is_string($token) && preg_match(self::FORMAT, $token) === 1 ? $token : null;
    }

    /** The cart this token opens; one query by the unique index. */
    public function find(?string $token): ?Cart
    {
        if ($token === null) {
            return null;
        }

        return Cart::query()->where('token', self::hash($token))->first();
    }

    /** The cookie for a token, extended on every write. */
    public function make(string $token, ?Request $request = null): Cookie
    {
        $request ??= request();
        $days = $this->config->get('webx-cart.cookie.days');
        $minutes = (is_numeric($days) ? (int) $days : 60) * 24 * 60;

        return $this->cookies->make($this->name(), $token, $minutes, '/', null, $request->isSecure(), true, false, 'lax');
    }

    public function forget(): Cookie
    {
        return $this->cookies->forget($this->name());
    }
}
