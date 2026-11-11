<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\InstalledPackages;
use WebxUi\Themes\ModuleStyles;
use WebxUi\Themes\ThemeAssets;

/**
 * SITE_MODULES §8, row 15: a module the site keeps in its own `modules/`, `site/module-*`, brings
 * its base look to `webx:theme:sync` like a packaged one.
 */
final class SiteModuleStylesTest extends TestCase
{
    use InstalledPackages;

    #[Test]
    public function a_site_module_is_one_of_the_installed_modules(): void
    {
        $this->installPackages(__DIR__.'/Fixtures/site-module/vendor/composer/installed.json');

        $offices = (string) realpath(__DIR__.'/Fixtures/site-module/modules/offices');
        $installed = ModuleStyles::installed();

        $this->assertContains($offices, $installed);

        $styles = new ModuleStyles($this->app->make(ThemeAssets::class), $installed);

        $this->assertArrayHasKey('offices/offices.css', $styles->sources());
        $this->assertStringContainsString("/* offices/offices.css */\n.wx-offices", $styles->css());
    }
}
