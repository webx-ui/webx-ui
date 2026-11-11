<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\Exceptions\ThemeException;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeManifest;
use WebxUi\Themes\ThemeReveal;

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
    public function the_nearest_layer_that_names_a_tab_decides_its_sections(): void
    {
        $top = new ThemeManifest('top', '/top', true, settings: ['contacts' => ['emails', 'socials']]);
        $silent = new ThemeManifest('silent', '/silent', false);
        $base = new ThemeManifest('base', '/base', false, settings: ['contacts' => ['phones', 'addresses', 'hours'], 'branding' => true]);

        // The child's list replaces the parent's; a tab the child leaves out is the parent's.
        $this->assertSame(['contacts' => ['emails', 'socials'], 'branding' => true], (new ThemeChain([$top, $silent, $base]))->settings());
        $this->assertSame(['top' => ['contacts' => ['emails', 'socials']], 'base' => ['branding' => true]], (new ThemeChain([$top, $silent, $base]))->settingsBy());
        // A child that declares nothing inherits the whole declaration.
        $this->assertSame(['contacts' => ['phones', 'addresses', 'hours'], 'branding' => true], (new ThemeChain([$silent, $base]))->settings());
        $this->assertTrue((new ThemeChain([new ThemeManifest('whole', '/whole', true, settings: ['contacts' => true]), $base]))->settings()['contacts'] ?? null);
        // An empty list drops the tab the parent printed.
        $this->assertSame([], (new ThemeChain([new ThemeManifest('bare', '/bare', true, settings: ['contacts' => []]), $base]))->settings()['contacts']);
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

    #[Test]
    public function the_nearest_layer_that_names_the_reveal_decides_and_nobody_naming_it_is_off(): void
    {
        $base = new ThemeManifest('base', '/base', false, reveal: 'fade');
        $silent = new ThemeManifest('silent', '/silent', true);

        $this->assertSame('fade', (new ThemeChain([$silent, $base]))->reveal());
        $this->assertSame('scale', (new ThemeChain([new ThemeManifest('top', '/top', true, reveal: 'scale'), $base]))->reveal());
        // A local theme can switch off what it stands on.
        $this->assertNull((new ThemeChain([new ThemeManifest('top', '/top', true, reveal: 'off'), $base]))->reveal());
        $this->assertNull((new ThemeChain([$silent]))->reveal());
    }

    #[Test]
    public function the_reveal_prints_its_attributes_only_while_the_theme_names_it(): void
    {
        $off = new ThemeReveal(new ThemeChain([new ThemeManifest('silent', '/silent', true)]));
        $on = new ThemeReveal(new ThemeChain([new ThemeManifest('top', '/top', true, reveal: 'up')]));

        $this->assertSame('', $off->attributes()->toHtml());
        $this->assertSame('', $off->attributes('stagger')->toHtml());
        $this->assertSame(' data-webx-reveal="up"', $on->attributes()->toHtml());
        $this->assertSame(' data-webx-reveal="stagger" data-webx-reveal-effect="up"', $on->attributes('stagger')->toHtml());
        $this->assertSame(' data-webx-reveal="fade"', $on->attributes('fade')->toHtml());
        // The block's own "no animation".
        $this->assertSame('', $on->attributes('', true)->toHtml());
        $this->assertSame('', $on->attributes('stagger', '1')->toHtml());
        $this->assertSame(' data-webx-reveal="up"', $on->attributes('', null)->toHtml());
    }

    #[Test]
    public function the_directive_asks_the_reveal(): void
    {
        $this->app->instance(ThemeReveal::class, new ThemeReveal(new ThemeChain([new ThemeManifest('top', '/top', true, reveal: 'scale')])));

        $this->assertSame('<ul  data-webx-reveal="stagger" data-webx-reveal-effect="scale"></ul><p ></p>', Blade::render("<ul @webxReveal('stagger')></ul><p @webxReveal('', \$off)></p>", ['off' => true], deleteCachedView: true));
    }

    #[Test]
    public function a_manifest_reads_its_reveal_and_refuses_another_word(): void
    {
        $dir = sys_get_temp_dir().'/webx-theme-reveal-'.getmypid();
        @mkdir($dir, 0777, true);

        try {
            foreach (['true' => 'up', 'false' => 'off', '"fade"' => 'fade', '"off"' => 'off'] as $json => $reveal) {
                file_put_contents($dir.'/theme.json', '{"theme": {"reveal": '.$json.'}}');
                $this->assertSame($reveal, ThemeManifest::fromLocal($dir, 'theme')->reveal, $json);
            }

            file_put_contents($dir.'/theme.json', '{"theme": {}}');
            $this->assertNull(ThemeManifest::fromLocal($dir, 'theme')->reveal);

            file_put_contents($dir.'/theme.json', '{"theme": {"reveal": "slide"}}');
            $this->expectException(ThemeException::class);
            $this->expectExceptionMessage('"reveal" is true, false or one of');
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
