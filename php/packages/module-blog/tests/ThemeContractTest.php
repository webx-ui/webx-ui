<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use WebxUi\Themes\Testing\ModuleThemeContract;

/** The feed's, a rubric's, a tag's and an article's base look and classes (THEMES §10, §18). */
final class ThemeContractTest extends PHPUnitTestCase
{
    use ModuleThemeContract;

    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }
}
