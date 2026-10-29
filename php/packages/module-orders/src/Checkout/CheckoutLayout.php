<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory;
use InvalidArgumentException;

/**
 * Which Blade component the checkout and «Thank you» stand in (spec §27.1).
 *
 * `webx-orders.checkout.layout` when the site names one (`'layout'` — the whole layout back);
 * otherwise `layout.minimal` of the theme — a header of the brand, no footer, no button of
 * contacts (decision 52) — wherever the site has it; otherwise what the buyer's page of an order
 * stands in (`webx-orders.layout`), so a site without the theme still gets whole pages.
 */
final class CheckoutLayout
{
    public const string MINIMAL = 'layout.minimal';

    public const string STANDALONE = 'webx-orders::standalone';

    public static function name(): string
    {
        $own = config('webx-orders.checkout.layout');

        if (is_string($own) && $own !== '') {
            return $own;
        }

        if (self::viewExists('components.'.self::MINIMAL)) {
            return self::MINIMAL;
        }

        $order = config('webx-orders.layout');

        return is_string($order) && $order !== '' ? $order : self::STANDALONE;
    }

    /** The theme's views join the finder at run time, out of sight of a static look at the disk. */
    private static function viewExists(string $view): bool
    {
        try {
            Container::getInstance()->make(Factory::class)->getFinder()->find($view);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
