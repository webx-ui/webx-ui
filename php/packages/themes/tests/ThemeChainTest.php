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

    #[Test]
    public function the_settings_a_theme_prints_are_summed_down_the_chain(): void
    {
        $top = new ThemeManifest('top', '/top', true, settings: ['contacts' => ['phones', 'hours']]);
        $silent = new ThemeManifest('silent', '/silent', false);
        $base = new ThemeManifest('base', '/base', false, settings: ['contacts' => ['emails', 'phones']]);

        $this->assertSame(['contacts' => ['phones', 'hours', 'emails']], (new ThemeChain([$top, $silent, $base]))->settings());
        $this->assertSame(['contacts' => true], (new ThemeChain([$top, new ThemeManifest('whole', '/whole', false, settings: ['contacts' => true])]))->settings());
        // Saying nothing is not saying "none": module-settings tells the two apart.
        $this->assertNull((new ThemeChain([$silent]))->settings());
        $this->assertSame([], (new ThemeChain([$silent, new ThemeManifest('none', '/none', false, settings: [])]))->settings());
    }

    #[Test]
    public function a_manifest_reads_its_settings_and_refuses_a_bad_shape(): void
    {
        $dir = sys_get_temp_dir().'/webx-theme-settings-'.getmypid();
        @mkdir($dir, 0777, true);

        try {
            file_put_contents($dir.'/theme.json', '{"theme": {"settings": {"contacts": ["phones", "emails"], "branding": true}}}');
            $this->assertSame(['contacts' => ['phones', 'emails'], 'branding' => true], ThemeManifest::fromLocal($dir, 'theme')->settings);

            file_put_contents($dir.'/theme.json', '{"theme": {}}');
            $this->assertNull(ThemeManifest::fromLocal($dir, 'theme')->settings);

            file_put_contents($dir.'/theme.json', '{"theme": {"settings": ["contacts"]}}');
            $this->expectException(ThemeException::class);
            $this->expectExceptionMessage('"settings" is an object of tabs');
            ThemeManifest::fromLocal($dir, 'theme');
        } finally {
            @unlink($dir.'/theme.json');
            @rmdir($dir);
        }
    }

    /**
     * @return list<string>
     */
    private function names(ThemeChain $chain): array
    {
        return array_map(fn (ThemeManifest $layer) => $layer->name, $chain->layers);
    }
}
