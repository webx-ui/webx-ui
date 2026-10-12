<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use WebxUi\Themes\Testing\ModuleThemeContract;

/** The index's, a category's, the catalogue's and a recipe's base look and classes (THEMES §10, §18). */
final class ThemeContractTest extends PHPUnitTestCase
{
    use ModuleThemeContract;

    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }
}
