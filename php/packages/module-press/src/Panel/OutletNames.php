<?php

declare(strict_types=1);

namespace WebxUi\Press\Panel;

use WebxUi\Localization\Locales;
use WebxUi\Press\Models\Outlet;

/**
 * What an outlet is called in the panel (§4.10): the name in the panel's language, else in any
 * language that has one, else its number — a row of the list is never blank.
 */
final class OutletNames
{
    public static function of(Outlet $outlet, Locales $locales): string
    {
        $title = $outlet->displayTitle($locales->current());

        return $title !== '' ? $title : '#'.$outlet->getKey();
    }
}
