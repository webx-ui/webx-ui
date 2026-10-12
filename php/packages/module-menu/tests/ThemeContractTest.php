<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use WebxUi\Themes\Testing\ModuleThemeContract;

/** The base look and classes of `<x-webx-menu::menu>` (THEMES §10, §18). */
final class ThemeContractTest extends PHPUnitTestCase
{
    use ModuleThemeContract;

    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }
}
