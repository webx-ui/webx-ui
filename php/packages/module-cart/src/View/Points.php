<?php

declare(strict_types=1);

namespace WebxUi\Cart\View;

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as Views;
use WebxUi\Admin\Support\Parts;

/**
 * The extension points of the panel (§9.2): `cart.line.after`, `cart.totals.before`,
 * `cart.totals.after`, `cart.footer` — where a satellite or a theme adds a row, a button, a hint.
 *
 * The same lookup as `@webxPart` of the panel's package — a customised block, else the theme's
 * `parts/cart/…` view, else nothing — but asked from here: the cart does not require that package,
 * and a directive a site may lack would print as text exactly where it is missing.
 */
final class Points
{
    /** @param  array<string, mixed>  $data */
    public static function render(string $point, array $data = []): string
    {
        if (class_exists(Parts::class)) {
            return Parts::render($point, $data, 'webx-cart::widget.point');
        }

        $views = Container::getInstance()->make(Views::class);
        $themed = 'parts.'.$point;

        return $views->exists($themed) ? $views->make($themed, $data)->render() : '';
    }
}
