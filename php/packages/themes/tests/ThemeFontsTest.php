<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Themes\Doctor\FontsCheck;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeFonts;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\Tokens;

/**
 * The fonts of the chain (spec §15.3): `@font-face` per script with its `unicode-range`, a family
 * of a higher layer replacing the one below, and the scripts a site's languages need — in
 * `webx:theme:check` and in `webx:doctor`.
 */
final class ThemeFontsTest extends TestCase
{
    private string $public;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->public = str_replace('\\', '/', sys_get_temp_dir()).'/webx-themes-fonts-'.bin2hex(random_bytes(6));
        mkdir($this->public);
        $app->usePublicPath($this->public);
        $app['config']->set('app.debug', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->public);

        parent::tearDown();
    }

    #[Test]
    public function the_head_prints_a_face_per_script_with_its_range_once_synced(): void
    {
        $this->assertStringContainsString(
            '<!-- webx-themes: fonts are not published (assets/fonts/sans-cyrillic.woff2 of webx-ui/theme-fixture, assets/fonts/sans-latin.woff2 of webx-ui/theme-fixture): php artisan webx:theme:sync -->',
            Blade::render('@webxTheme'),
        );

        $this->artisan('webx:theme:sync')->assertSuccessful();

        $html = Blade::render('@webxTheme');
        $this->assertStringContainsString('<style data-webx-fonts>', $html);
        $this->assertStringNotContainsString('fonts are not published', $html);
        $this->assertMatchesRegularExpression(
            '~@font-face \{\n  font-family: "Fixture Sans";\n  font-style: normal;\n  font-weight: 100 900;\n  font-display: swap;\n'
            .'  src: url\("http://localhost/themes/webx-ui/theme-fixture/[0-9a-f]{12}/assets/fonts/sans-cyrillic\.woff2"\) format\("woff2"\);\n'
            .'  unicode-range: U\+0301, U\+0400-045F, U\+0490-0491, U\+04B0-04B1, U\+2116;\n\}~',
            $html,
        );

        // Latin last, whatever order the manifest wrote the files in: where ranges overlap, the
        // face declared last wins, and that should be the file every page loads anyway.
        $this->assertLessThan(strpos($html, 'sans-latin.woff2'), (int) strpos($html, 'sans-cyrillic.woff2'));

        // Before the tokens' stylesheets, after the tokens.
        $this->assertLessThan(strpos($html, '<style data-webx-fonts>'), (int) strpos($html, '<style data-webx-theme>'));
    }

    #[Test]
    public function a_family_of_a_higher_layer_replaces_the_same_family_below_whole(): void
    {
        $directory = $this->localTheme([
            ['family' => 'fixture sans', 'files' => ['latin' => 'fonts/mine.woff2']],
            ['family' => 'Display', 'style' => 'italic', 'files' => ['latin' => 'fonts/display.woff2']],
        ]);

        try {
            $faces = ThemeFonts::faces(ThemeChain::resolve($directory, $this->app->make(ThemeLocator::class)));

            $this->assertSame(['fixture sans', 'Display'], array_column($faces, 'family'));
            $this->assertSame(['latin' => 'fonts/mine.woff2'], $faces[0]['files']);
            $this->assertSame('400', $faces[0]['weight']);
            $this->assertSame('italic', $faces[1]['style']);
        } finally {
            (new Filesystem)->deleteDirectory($directory);
        }
    }

    #[Test]
    public function an_entry_the_page_cannot_print_is_skipped(): void
    {
        $this->assertNull(ThemeFonts::face(['family' => 'A"B', 'files' => ['latin' => 'fonts/a.woff2']]));
        $this->assertNull(ThemeFonts::face(['family' => 'A', 'files' => ['klingon' => 'fonts/a.woff2']]));
        $this->assertNull(ThemeFonts::face(['family' => 'A', 'files' => ['latin' => 'https://cdn.example/a.woff2']]));
        $this->assertNull(ThemeFonts::face(['family' => 'A', 'files' => ['latin' => 'fonts/a.ttf']]));
        $this->assertNull(ThemeFonts::face(['family' => 'A', 'weight' => 'bold', 'files' => ['latin' => 'fonts/a.woff2']]));
        $this->assertNull(ThemeFonts::face(['family' => 'A', 'files' => 'fonts/*.woff2']));
        $this->assertSame(['latin' => 'fonts/a.woff'], ThemeFonts::face(['family' => 'A', 'files' => ['latin' => '/fonts/a.woff']])['files'] ?? null);
    }

    #[Test]
    public function a_language_needs_latin_and_its_own_script(): void
    {
        $this->assertSame(['latin'], ThemeFonts::needs('en'));
        $this->assertSame(['latin'], ThemeFonts::needs('de'));
        $this->assertSame(['latin', 'latin-ext'], ThemeFonts::needs('pl'));
        $this->assertSame(['latin', 'latin-ext'], ThemeFonts::needs('tr'));
        $this->assertSame(['latin', 'cyrillic'], ThemeFonts::needs('ru'));
        $this->assertSame(['latin', 'cyrillic'], ThemeFonts::needs('uk'));
        $this->assertSame(['latin'], ThemeFonts::needs('pt-BR'));
        $this->assertNull(ThemeFonts::needs('ja'));
    }

    #[Test]
    public function check_names_every_entry_it_cannot_read_and_every_script_a_language_misses(): void
    {
        $this->artisan('webx:theme:check', ['path' => self::fixture('fonts-theme'), '--locales' => 'en,ru,el'])
            ->expectsOutputToContain('fonts-theme/theme.json  fonts[0]: latin-ext: assets/fonts/inter-latin-ext.woff2 does not exist')
            ->expectsOutputToContain('fonts[0]: "klingon" is not a script the check knows')
            ->expectsOutputToContain('fonts[0]: cyrillic: a path under assets/, not a URL')
            ->expectsOutputToContain('fonts[1]: "family" is the name of the font')
            ->expectsOutputToContain('fonts[1]: latin: assets/fonts/serif.woff2 does not exist')
            ->expectsOutputToContain('fonts[2]: "scripts" is not read')
            ->expectsOutputToContain('fonts[2]: "files" maps each script to its file under assets/')
            ->expectsOutputToContain('fonts[3]: "weight" is "400"')
            ->expectsOutputToContain('fonts[3]: "style" is "normal" or "italic"')
            ->expectsOutputToContain('fonts[3]: latin: assets/fonts/serif.ttf is not a web font')
            // The site's ru and the theme's own uk; the site's el.
            ->expectsOutputToContain('font "Inter" normal 400 has no file for cyrillic, which ru, uk need')
            ->expectsOutputToContain('font "Inter" normal 400 has no file for greek, which el needs')
            ->expectsOutputToContain('theme-fixture/composer.json  font "Fixture Sans" normal 100 900 has no file for greek, which el needs')
            ->expectsOutputToContain('13 findings')
            ->assertFailed();
    }

    #[Test]
    public function a_clean_chain_says_which_fonts_cover_which_languages(): void
    {
        $this->artisan('webx:theme:check', ['--locales' => 'en,ru'])
            ->expectsOutputToContain('fonts Fixture Sans in latin, cyrillic, every script of en, ru')
            ->assertSuccessful();

        $this->artisan('webx:theme:check', ['--locales' => 'pl'])
            ->expectsOutputToContain('font "Fixture Sans" normal 100 900 has no file for latin-ext, which pl needs')
            ->assertFailed();
    }

    #[Test]
    public function without_a_locales_option_the_site_languages_are_compared(): void
    {
        $this->app['config']->set('app.locale', 'en');

        $this->artisan('webx:theme:check')
            ->expectsOutputToContain('every script of en;')
            ->assertSuccessful();
    }

    #[Test]
    public function the_doctor_warns_about_a_script_the_site_publishes_in_and_the_font_lacks(): void
    {
        $this->assertContains(FontsCheck::class, $this->app->make(DoctorChecks::class)->all());

        $this->app['config']->set('app.locale', 'en');
        $ok = $this->app->make(FontsCheck::class)->run();
        $this->assertCount(1, $ok);
        $this->assertSame(Diagnosis::OK, $ok[0]->state);
        $this->assertSame('Fixture Sans — a file for every script of en.', $ok[0]->detail);

        $this->app['config']->set('app.locale', 'pl');
        $warned = $this->app->make(FontsCheck::class)->run();
        $this->assertCount(1, $warned);
        $this->assertSame(Diagnosis::WARN, $warned[0]->state);
        $this->assertStringContainsString('Fixture Sans (normal, of webx-ui/theme-fixture) has no latin-ext file, and the site publishes in pl', $warned[0]->detail);
    }

    #[Test]
    public function without_the_options_the_head_has_no_preload_and_no_fallback(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        $html = Blade::render('@webxTheme');

        $this->assertStringNotContainsString('rel="preload"', $html);
        $this->assertStringNotContainsString('Fallback', $html);
        $this->assertStringContainsString('--site-font-heading: "Inter", sans-serif;', $html);
    }

    #[Test]
    public function a_face_with_preload_preloads_the_files_of_the_page_language_only(): void
    {
        $this->useTheme(self::fixture('font-options-theme'));
        $this->artisan('webx:theme:sync')->assertSuccessful();

        $this->app->setLocale('en');
        $html = Blade::render('@webxTheme');
        $this->assertMatchesRegularExpression('~<link rel="preload" href="http://localhost/themes/[^"]+/assets/fonts/options-latin\.woff2" as="font" type="font/woff2" crossorigin>~', $html);
        $this->assertStringNotContainsString('options-cyrillic.woff2" as="font"', $html);
        // The fixture's own face does not ask for it.
        $this->assertStringNotContainsString('sans-latin.woff2" as="font"', $html);
        $this->assertLessThan(strpos($html, '<style data-webx-fonts>'), (int) strpos($html, 'rel="preload"'));

        $this->app->setLocale('uk');
        $html = Blade::render('@webxTheme');
        $this->assertStringContainsString('options-cyrillic.woff2" as="font"', $html);
        $this->assertStringNotContainsString('options-latin.woff2" as="font"', $html);
    }

    #[Test]
    public function the_scripts_a_page_preloads_are_its_alphabet(): void
    {
        $this->assertSame(['latin'], ThemeFonts::preloaded('en'));
        $this->assertSame(['latin'], ThemeFonts::preloaded('pl'));
        $this->assertSame(['cyrillic'], ThemeFonts::preloaded('ru'));
        $this->assertSame(['cyrillic'], ThemeFonts::preloaded('kk'));
        $this->assertSame(['greek'], ThemeFonts::preloaded('el'));
        $this->assertSame(['latin'], ThemeFonts::preloaded('ja'));
    }

    #[Test]
    public function a_fallback_is_a_local_face_with_the_metrics_and_follows_its_font_in_the_stack(): void
    {
        $this->useTheme(self::fixture('font-options-theme'));

        $html = Blade::render('@webxTheme');

        $this->assertStringContainsString(implode("\n", [
            '@font-face {',
            '  font-family: "Options Sans Fallback";',
            '  src: local("Arial"), local("Helvetica");',
            '  size-adjust: 101.45%;',
            '  ascent-override: 93.64%;',
            '  descent-override: 24.64%;',
            '  line-gap-override: 0%;',
            '}',
        ]), $html);
        $this->assertStringContainsString('--site-font-body: "Options Sans", "Options Sans Fallback", system-ui, sans-serif;', $html);
        // A stack that does not name the font keeps its own.
        $this->assertStringContainsString('--site-font-heading: "Inter", sans-serif;', $html);
    }

    #[Test]
    public function a_stack_gets_the_fallback_once_and_only_after_its_font(): void
    {
        $fallbacks = ['inter' => 'Inter Fallback'];

        $this->assertSame('Inter, "Inter Fallback", Arial', ThemeFonts::withFallbacks('Inter, Arial', $fallbacks));
        $this->assertSame('"Inter", "Inter Fallback", sans-serif', ThemeFonts::withFallbacks('"Inter", sans-serif', $fallbacks));
        $this->assertSame('Inter, "Inter Fallback", Arial', ThemeFonts::withFallbacks('Inter, "Inter Fallback", Arial', $fallbacks));
        $this->assertSame('Roboto, Arial', ThemeFonts::withFallbacks('Roboto, Arial', $fallbacks));
        $this->assertSame('var(--site-font-body)', ThemeFonts::withFallbacks('var(--site-font-body)', $fallbacks));
    }

    #[Test]
    public function a_malformed_option_is_dropped_on_the_page_and_named_by_the_check(): void
    {
        $face = ThemeFonts::face(['family' => 'A', 'preload' => 'yes', 'fallback' => ['size-adjust' => '101'], 'files' => ['latin' => 'fonts/a.woff2']]);

        $this->assertNotNull($face);
        $this->assertFalse($face['preload']);
        $this->assertNull($face['fallback']);
        $this->assertNull(ThemeFonts::fallback(['local' => 'Ar"ial']));
        $this->assertNull(ThemeFonts::fallback(['font' => 'Arial']));
        $this->assertSame(['local' => ['Arial']], ThemeFonts::fallback([]));

        $directory = $this->localTheme([
            ['family' => 'Bad', 'preload' => 'yes', 'fallback' => ['size' => '1'], 'files' => ['latin' => 'fonts/bad.woff2']],
        ]);

        try {
            $this->artisan('webx:theme:check', ['path' => $directory, '--locales' => 'en'])
                ->expectsOutputToContain('fonts[0]: "preload" is true or false')
                ->expectsOutputToContain('fonts[0]: "fallback" is {')
                ->assertFailed();
        } finally {
            (new Filesystem)->deleteDirectory($directory);
        }
    }

    /** The chain on another theme for the rest of the test: what reads the chain is made again. */
    private function useTheme(string $directory): void
    {
        $this->app['config']->set('webx-themes.theme', $directory);
        $this->app->forgetInstance(ThemeChain::class);
        $this->app->forgetInstance(Tokens::class);
    }

    /**
     * A local theme on the fixture with the given fonts.
     *
     * @param  list<array<string, mixed>>  $fonts
     */
    private function localTheme(array $fonts): string
    {
        $directory = str_replace('\\', '/', sys_get_temp_dir()).'/webx-fonts-'.bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory.'/theme.json', json_encode(['theme' => ['uses' => ['webx-ui/theme-fixture'], 'fonts' => $fonts]]));

        return $directory;
    }
}
