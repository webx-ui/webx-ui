<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\AssertsHelp;
use WebxUi\Catalog\Manticore\ManticoreServiceProvider;
use WebxUi\Catalog\Tests\TestCase;

/**
 * The page of the search index is explained like any other (WEBX_UI_PANEL_HELP.md §6).
 */
final class HelpCoverageTest extends TestCase
{
    use AssertsHelp;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ManticoreServiceProvider::class];
    }

    #[Test]
    public function every_screen_and_field_is_explained(): void
    {
        $this->assertScreensHaveHelp();
    }
}
