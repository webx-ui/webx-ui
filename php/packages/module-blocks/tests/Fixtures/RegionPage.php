<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests\Fixtures;

use WebxUi\Routing\HasUrl;

/**
 * A page with an address, answered by a handler whose view stands in a layout that prints the
 * regions — what a site with `<x-webx-blocks::region>` in its layout looks like.
 */
final class RegionPage extends Page
{
    use HasUrl;
}
