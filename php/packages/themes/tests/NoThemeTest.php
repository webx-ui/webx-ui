<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\ThemeChain;

class NoThemeTest extends TestCase
{
    protected function theme(): string
    {
        return '';
    }

    #[Test]
    public function an_empty_setting_leaves_the_views_alone(): void
    {
        $this->assertTrue(app(ThemeChain::class)->isEmpty());
        $this->assertSame('site', $this->layerOf('site-wins'));
        $this->assertSame('module', $this->layerOf('webx-fake::default-wins'));
        $this->assertCount(2, $this->finder()->getHints()['webx-fake']);
        $this->assertSame('', Blade::render('@webxTheme'), 'the directive stays registered for a layout that has it');
        $this->assertNull(theme_token('color-accent'));

        $this->expectException(InvalidArgumentException::class);
        $this->finder()->find('local-wins');
    }
}
