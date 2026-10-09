<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\Exceptions\ThemeException;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeManifest;

class ThemeChainTest extends TestCase
{
    #[Test]
    public function the_configured_chain_is_local_then_its_package(): void
    {
        $chain = app(ThemeChain::class);

        $this->assertSame([self::fixture('site/theme'), 'webx-ui/theme-fixture'], $this->names($chain));
        $this->assertTrue($chain->top()?->local);
        $this->assertSame('Local', $chain->top()->title);
        $this->assertSame(['module-pages', 'module-blocks'], $chain->requires());
    }

    #[Test]
    public function a_package_reads_its_manifest_from_composer_extra(): void
    {
        $manifest = ThemeManifest::fromPackage(self::fixture('theme-fixture'));

        $this->assertSame('webx-ui/theme-fixture', $manifest->name);
        $this->assertSame('Fixture', $manifest->title);
        $this->assertFalse($manifest->local);
        $this->assertSame([], $manifest->uses);
    }

    #[Test]
    public function a_shared_base_sits_once_below_everything_that_uses_it(): void
    {
        $chain = ThemeChain::resolve('fixture/diamond-top', app(ThemeLocator::class));

        $this->assertSame(
            ['fixture/diamond-top', 'fixture/diamond-left', 'fixture/diamond-right', 'webx-ui/theme-fixture'],
            $this->names($chain),
        );
    }

    #[Test]
    public function a_loop_in_uses_is_an_error_at_load(): void
    {
        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage('fixture/cycle-a -> fixture/cycle-b -> fixture/cycle-a');

        ThemeChain::resolve('fixture/cycle-a', app(ThemeLocator::class));
    }

    #[Test]
    public function a_missing_theme_names_who_asked_for_it(): void
    {
        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage('Theme [nowhere] was not found');

        ThemeChain::resolve('nowhere', app(ThemeLocator::class));
    }

    #[Test]
    public function a_package_that_is_not_a_theme_is_refused(): void
    {
        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage('"type": "webx-theme"');

        ThemeChain::resolve('fixture/not-a-theme', app(ThemeLocator::class));
    }

    /**
     * @return list<string>
     */
    private function names(ThemeChain $chain): array
    {
        return array_map(fn (ThemeManifest $layer) => $layer->name, $chain->layers);
    }
}
