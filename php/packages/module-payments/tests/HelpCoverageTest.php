<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\AssertsHelp;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Notifications\NotificationsServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;

/**
 * Every screen this package draws or patches is explained, field by field (WEBX_UI_PANEL_HELP.md §6).
 */
final class HelpCoverageTest extends TestCase
{
    use AssertsHelp;

    /**
     * The settings too, and what their screen needs: the field of this package on it is a patch of
     * somebody else's screen, and without its owner nothing would hold its description.
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), MediaServiceProvider::class, NotificationsServiceProvider::class, SettingsServiceProvider::class];
    }

    #[Test]
    public function every_screen_and_field_is_explained(): void
    {
        $this->assertScreensHaveHelp();
    }
}
