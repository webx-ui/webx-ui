<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\AssertsHelp;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;

/**
 * Every screen this package draws or patches is explained, field by field (WEBX_UI_PANEL_HELP.md §6).
 */
final class HelpCoverageTest extends TestCase
{
    use AssertsHelp;

    /**
     * The settings too: the widgets this package needs describe their tabs there, and a description
     * of a screen nobody loaded is red.
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), MediaServiceProvider::class, SettingsServiceProvider::class];
    }

    #[Test]
    public function every_screen_and_field_is_explained(): void
    {
        $this->assertScreensHaveHelp();
    }
}
