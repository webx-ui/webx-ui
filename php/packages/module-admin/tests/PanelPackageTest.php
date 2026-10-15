<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use WebxUi\Admin\Panel\PanelPackage;

final class PanelPackageTest extends PhpUnitTestCase
{
    /** `extra.webx.settings`: the tabs of the site's settings a package prints, read for module-settings. */
    #[Test]
    public function a_package_says_which_settings_tabs_it_prints(): void
    {
        $package = PanelPackage::fromExtra('acme/contacts-widget', ['webx' => ['settings' => [
            'contacts' => ['phones', '', 7, 'hours'],
            'branding' => true,
            'nonsense' => 'yes',
            0 => true,
        ]]]);

        $this->assertNotNull($package);
        $this->assertSame(['contacts' => ['phones', 'hours'], 'branding' => true], $package->settings);
        $this->assertSame([], PanelPackage::fromExtra('acme/quiet', ['webx' => ['module' => 'quiet']])?->settings);
    }
}
