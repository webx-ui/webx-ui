<?php

declare(strict_types=1);

namespace WebxUi\Banners\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\AssertsHelp;

/**
 * Every screen this package draws or patches is explained, field by field (WEBX_UI_PANEL_HELP.md §6).
 */
final class HelpCoverageTest extends TestCase
{
    use AssertsHelp;

    #[Test]
    public function every_screen_and_field_is_explained(): void
    {
        $this->assertScreensHaveHelp();
    }
}
