<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Services\Models\ServiceCategory;

/**
 * The tab is in the JSON of the screen and a site that did not ask for it loses it to a patch, so
 * the help can describe it on every site. `ProjectFieldsTest` keeps the site that asked for it.
 */
final class CategoryBlocksTabTest extends TestCase
{
    #[Test]
    public function a_site_that_did_not_ask_for_the_blocks_has_no_tab_and_no_field(): void
    {
        $tree = Screens::tree(ServiceCategory::SCREEN);

        $this->assertSame(['content', 'seo'], array_column(Tree::children($tree[0]), 'id'));
        $this->assertNotContains('blocks', array_column(Screens::fields(ServiceCategory::SCREEN), 'id'));
    }
}
