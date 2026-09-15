<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests\Fixtures;

use WebxUi\Routing\HasUrl;

/**
 * The same page with an address in the registry — the shape `module-pages` will give it. Kept
 * apart from {@see Page} so that the rendering tests, which make pages by the dozen with no
 * slug, do not have to compete for the one address an empty slug formats to.
 */
final class RoutedPage extends Page
{
    use HasUrl;
}
