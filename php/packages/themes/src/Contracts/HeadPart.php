<?php

declare(strict_types=1);

namespace WebxUi\Themes\Contracts;

/**
 * Something a package below the themes adds to what `@webxTheme` prints — the widgets'
 * stylesheets, and later the base look of the modules (spec §7.1, §10.2, §11).
 *
 * Tagged `webx-themes.head` in the container. Printed after the tokens and before the layers'
 * stylesheets: the bottom of the chain comes first in the cascade, so any theme overrides it
 * by just writing its rule. Only on a site with a theme — without one `@webxTheme` prints
 * nothing at all.
 */
interface HeadPart
{
    public const string TAG = 'webx-themes.head';

    public function head(): string;
}
