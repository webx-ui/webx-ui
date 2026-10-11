<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeIcons;

/**
 * The icons of the chain (spec §13.1): one sprite from the layers' `icons/`, the higher layer
 * winning by name, published by sync and pointed at by `<x-theme::icon>`; `webx:theme:check`
 * reads the files and the templates that name them.
 */
final class ThemeIconsTest extends TestCase
{
    private string $public;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->public = str_replace('\\', '/', sys_get_temp_dir()).'/webx-themes-icons-'.bin2hex(random_bytes(6));
        mkdir($this->public);
        $app->usePublicPath($this->public);
        $app['config']->set('app.debug', false);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->public);

        parent::tearDown();
    }

    #[Test]
    public function the_higher_layer_wins_by_name(): void
    {
        $icons = $this->app->make(ThemeIcons::class);

        $this->assertSame(['arrow-right', 'phone', 'star'], $icons->names());
        $this->assertSame('webx-ui/theme-fixture', $icons->sources()['arrow-right']['layer']);
        $this->assertStringEndsWith('site/theme/icons/phone.svg', $icons->sources()['phone']['file']);

        $sprite = $icons->sprite();
        $this->assertStringContainsString('<symbol id="phone" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8" data-layer="local"/></symbol>', $sprite);
        $this->assertStringNotContainsString('M5 4h4', $sprite);
    }

    #[Test]
    public function a_symbol_keeps_the_paint_of_the_root_and_drops_what_an_icon_should_not_carry(): void
    {
        $this->assertSame(
            '<symbol id="line" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12h16"/></symbol>',
            ThemeIcons::symbol('line', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0,0,24,24" fill="none" stroke="currentColor" stroke-width="2" class="x"><path d="M4 12h16"/></svg>'),
        );

        // A size and no viewBox, a prolog, a handler, a script, a stylesheet.
        $this->assertSame(
            '<symbol id="arrow" viewBox="0 0 16 16"><path d="M2 8h12" stroke="currentColor"/></symbol>',
            ThemeIcons::symbol('arrow', '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16" onload="x()"><script>x()</script><style>path{}</style><path d="M2 8h12" stroke="currentColor" onclick="y()"/></svg>'),
        );

        $this->assertNull(ThemeIcons::symbol('nothing', '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>'));
        $this->assertNull(ThemeIcons::symbol('html', '<p>not an icon</p>'));
        $this->assertNull(ThemeIcons::symbol('broken', '<svg'));
    }

    #[Test]
    public function sync_publishes_the_sprite_and_the_tag_points_at_it(): void
    {
        $this->assertSame(0, Artisan::call('webx:theme:sync'));
        $this->assertMatchesRegularExpression('/icons — the sprite of the chain \.* published [0-9a-f]{12} \(3 icons\)/', Artisan::output());

        $icons = $this->app->make(ThemeIcons::class);
        $hash = $icons->current();
        $this->assertNotNull($hash);
        $this->assertSame($icons->hash(), $hash);
        $this->assertFileExists($this->public.'/themes/icons/'.$hash.'/icons.svg');
        $this->assertStringContainsString('<symbol id="star"', (string) file_get_contents($this->public.'/themes/icons/'.$hash.'/icons.svg'));

        $html = Blade::render('<x-theme::icon name="phone" class="b-hero__icon" />');
        $this->assertSame(
            '<svg class="theme-icon b-hero__icon" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false"><use href="http://localhost/themes/icons/'.$hash.'/icons.svg#phone"/></svg>',
            trim($html),
        );

        // A name for a picture that means something; a size given to the tag wins over 1em.
        $named = Blade::render('<x-theme::icon name="star" label="Favourite" width="24" height="24" />');
        $this->assertStringContainsString('width="24" height="24"', $named);
        $this->assertStringContainsString('role="img" aria-label="Favourite"', $named);
        $this->assertStringNotContainsString('aria-hidden', $named);

        $this->artisan('webx:theme:sync')->expectsOutputToContain('unchanged')->assertSuccessful();
    }

    #[Test]
    public function an_unknown_icon_or_an_unpublished_sprite_is_an_empty_svg_and_a_hint_in_debug(): void
    {
        $empty = '<svg class="theme-icon" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false"></svg>';

        $this->assertSame($empty, trim(Blade::render('<x-theme::icon name="phone" />')));
        $this->assertSame($empty, trim(Blade::render('<x-theme::icon name="fax" />')));

        $this->app['config']->set('app.debug', true);
        $this->assertStringContainsString('the icon sprite is not published: php artisan webx:theme:sync', Blade::render('<x-theme::icon name="phone" />'));
        $this->assertStringContainsString('no icon fax in the chain: add icons/fax.svg to a layer', Blade::render('<x-theme::icon name="fax" />'));
    }

    #[Test]
    public function without_a_theme_the_tag_still_compiles_to_an_empty_svg(): void
    {
        $this->app->instance(ThemeChain::class, new ThemeChain);
        $this->app->forgetScopedInstances();

        $this->assertSame([], $this->app->make(ThemeIcons::class)->names());
        $this->assertStringContainsString('<svg class="theme-icon"', Blade::render('<x-theme::icon name="phone" />'));
        $this->artisan('webx:theme:sync')->expectsOutputToContain('No theme is configured')->assertSuccessful();
    }

    #[Test]
    public function the_check_reads_the_icon_files_and_the_templates_that_name_them(): void
    {
        $this->artisan('webx:theme:check', ['path' => self::fixture('icons-theme')])
            ->expectsOutputToContain('icons/Big_Arrow.svg  the name "Big_Arrow" is not lowercase letters')
            ->expectsOutputToContain('icons/Big_Arrow.svg  no viewBox')
            ->expectsOutputToContain('icons/logo.svg  literal colour fill="#e11d48" — the icon does not take the colour of the text')
            ->expectsOutputToContain('icons/logo.svg  literal colour stroke: #000')
            ->expectsOutputToContain('icons/logo.svg  <style> — the sprite drops it')
            ->expectsOutputToContain('icons/handler.svg  <script> — the sprite drops it')
            ->expectsOutputToContain('icons/handler.svg  onload — the sprite drops it')
            ->expectsOutputToContain('card.blade.php:5  icon "fax" — no layer of the chain has icons/fax.svg')
            ->expectsOutputToContain('card.blade.php:7  inline <svg>')
            ->expectsOutputToContain('9 findings')
            ->assertFailed();
    }

    #[Test]
    public function a_clean_chain_counts_its_icons(): void
    {
        $this->artisan('webx:theme:check')
            ->expectsOutputToContain('3 icons, every one its templates name')
            ->assertSuccessful();
    }
}
