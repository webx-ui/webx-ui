<?php

declare(strict_types=1);

namespace WebxUi\Admin\Contracts;

use WebxUi\Admin\Manifest\Branding;

/**
 * Where the panel's own name and logo come from.
 *
 * The frame cannot ask the settings for them itself: `module-admin` knows nothing about
 * `module-settings`, and must keep working on a site that does not install it. So it asks
 * whoever is bound to this contract, and when nobody is, wears its configured title.
 *
 * There is one brand, so this is a binding and not a registry: a second source would only
 * raise the question of which of two logos wins.
 */
interface BrandingSource
{
    public function branding(): Branding;
}
