<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * `webx/orders/checkout.js` — the script of the checkout (spec §27.3): «Change» of a folded step,
 * the column redrawn, the sign-in taken over, the sticky row. Plain JavaScript served as written
 * — a site installs the package from Composer with no Node to build it with — and immutable: the
 * address carries a hash of the contents.
 */
final class ScriptController
{
    public const string SCRIPT = __DIR__.'/../../../resources/js/checkout.js';

    public const string ROUTE = 'webx-orders.checkout.script';

    private static ?string $version = null;

    public function __invoke(): Response
    {
        return new Response(self::contents(), 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.self::version().'"',
        ]);
    }

    public static function url(): string
    {
        return route(self::ROUTE, ['v' => self::version()]);
    }

    public static function contents(): string
    {
        return (string) @file_get_contents(self::SCRIPT);
    }

    public static function version(): string
    {
        return self::$version ??= substr(hash('xxh128', self::contents()), 0, 16);
    }
}
