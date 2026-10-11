<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use WebxUi\Themes\Testing\ModuleThemeContract;

/** The form's base look and its classes, as a theme relies on them (THEMES §10, §18). */
final class ThemeContractTest extends PHPUnitTestCase
{
    use ModuleThemeContract;

    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }
}
