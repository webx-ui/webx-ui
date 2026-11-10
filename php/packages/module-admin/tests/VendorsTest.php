<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as BaseTestCase;
use WebxUi\Admin\Panel\Vendors;

/** Which packages are ours, and which of them are the site's own (SITE_MODULES §8). */
final class VendorsTest extends BaseTestCase
{
    #[Test]
    public function ours_are_the_monorepos_and_the_sites(): void
    {
        $this->assertTrue(Vendors::ours('webx-ui/module-pages'));
        $this->assertTrue(Vendors::ours('site/module-offices'));
        $this->assertFalse(Vendors::ours('acme/module-widgets'));
        $this->assertFalse(Vendors::ours('webx-uikit/module-x'), 'a vendor is a whole name');

        $this->assertTrue(Vendors::local('site/module-offices'));
        $this->assertFalse(Vendors::local('webx-ui/module-pages'));

        $this->assertTrue(Vendors::module('webx-ui/module-pages'));
        $this->assertTrue(Vendors::module('site/module-offices'));
        $this->assertFalse(Vendors::module('webx-ui/themes'));
        $this->assertFalse(Vendors::module('acme/module-widgets'));
    }
}
