<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use WebxUi\Themes\Testing\ModuleThemeContract;

/** The widget's base look and classes (THEMES §10, §18; §9.6). */
final class ThemeContractTest extends PHPUnitTestCase
{
    use ModuleThemeContract;

    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }
}
