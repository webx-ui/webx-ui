<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use WebxUi\Admin\AbstractModule;

/**
 * Deliberately plain: everything but the id comes from the defaults.
 */
final class MediaModule extends AbstractModule
{
    public function id(): string
    {
        return 'media-library';
    }
}
