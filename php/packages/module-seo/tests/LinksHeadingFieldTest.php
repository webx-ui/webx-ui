<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Settings\Settings;

/**
 * The heading is in the JSON of the tab and a site without interlinking loses it to a patch, so the
 * help can describe it on every site. `LinksTest` keeps the site that has interlinking.
 */
final class LinksHeadingFieldTest extends TestCase
{
    #[Test]
    public function a_site_without_interlinking_has_no_heading_field(): void
    {
        $this->assertNotContains('links-heading', array_column(Screens::fields(Settings::SCREEN), 'id'));
    }
}
