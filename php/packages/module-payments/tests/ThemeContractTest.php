<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use WebxUi\Themes\Testing\ModuleThemeContract;

/** The step's and the bank details' base look and classes (THEMES §10, §18; §10.3 of the spec). */
final class ThemeContractTest extends PHPUnitTestCase
{
    use ModuleThemeContract;

    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }
}
