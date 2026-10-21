<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\Checks\ContrastCheck;
use WebxUi\Themes\Exceptions\ThemeException;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeHead;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeReport;
use WebxUi\Themes\Tokens;

/**
 * The dark scheme of a chain (spec §7.6): `schemes.dark` of every layer merged up it, printed by
 * `@webxTheme` under `prefers-color-scheme` and `data-theme`, held to the contrast of rule 29 and
 * shown by `webx:theme:show` — while a letter keeps the light values.
 *
 * The test theme: a package with the light and the dark palette, and a local theme over it that
 * retunes one dark token and recolours one light token without its dark pair.
 */
final class DarkSchemeTest extends TestCase
{
    private const string PARENT = 'fixture/dark-parent';

    private static ?string $root = null;

    /** @var list<string> */
    private array $temporary = [];

    public static function tearDownAfterClass(): void
    {
        if (self::$root !== null) {
            (new Filesystem)->deleteDirectory(self::$root);
            self::$root = null;
        }

        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporary as $directory) {
            (new Filesystem)->deleteDirectory($directory);
        }

        parent::tearDown();
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->resolving(ThemeLocator::class, function (ThemeLocator $locator): void {
            $locator->register(self::PARENT, self::root().'/parent');
        });
    }

    protected function theme(): string
    {
        return self::root().'/site';
    }

    #[Test]
    public function the_dark_values_merge_up_the_chain_and_say_where_they_come_from(): void
    {
        $tokens = app(Tokens::class);

        $this->assertSame([
            'color-bg' => '#0D0F0E',
            'color-surface' => '#121413',
            'color-text' => '#ECEDE8',
            'color-text-muted' => '#989D99',
            'color-border' => '#2A2E2C',
            'color-accent' => '#9AA8FF',
            'color-accent-contrast' => '#0D0F0E',
            'color-danger' => '#F0765F',
            'color-success' => '#1F8A5B',
        ], $tokens->dark());

        $this->assertSame(self::root().'/site', $tokens->darkSources()['color-accent'], 'the local theme retuned one dark token');
        $this->assertSame(self::PARENT, $tokens->darkSources()['color-bg'], 'the rest come from the package');
        $this->assertSame('#2E49F5', $tokens->get('color-accent'), 'the light value is not touched by the dark one');
    }

    #[Test]
    public function the_theme_prints_the_dark_values_under_the_system_scheme_and_under_data_theme(): void
    {
        $this->assertSame(<<<'CSS'
            :root {
              --site-color-bg: #F7F7F4;
              --site-color-surface: #F4F5F1;
              --site-color-text: #121413;
              --site-color-text-muted: #555A57;
              --site-color-border: #B0B3AC;
              --site-color-accent: #2E49F5;
              --site-color-accent-contrast: var(--site-color-bg);
              --site-color-highlight: #FFE08A;
              --site-color-danger: #B3261E;
              --site-color-success: #1F8A5B;
              color-scheme: light dark;
            }
            @media (prefers-color-scheme: dark) {
              :root:not([data-theme="light"]) {
                --site-color-bg: #0D0F0E;
                --site-color-surface: #121413;
                --site-color-text: #ECEDE8;
                --site-color-text-muted: #989D99;
                --site-color-border: #2A2E2C;
                --site-color-accent: #9AA8FF;
                --site-color-accent-contrast: #0D0F0E;
                --site-color-danger: #F0765F;
                --site-color-success: #1F8A5B;
              }
            }
            :root[data-theme="dark"] {
              --site-color-bg: #0D0F0E;
              --site-color-surface: #121413;
              --site-color-text: #ECEDE8;
              --site-color-text-muted: #989D99;
              --site-color-border: #2A2E2C;
              --site-color-accent: #9AA8FF;
              --site-color-accent-contrast: #0D0F0E;
              --site-color-danger: #F0765F;
              --site-color-success: #1F8A5B;
              color-scheme: dark;
            }
            :root[data-theme="light"] {
              color-scheme: light;
            }
            CSS, app(Tokens::class)->css());

        $this->assertStringContainsString("<style data-webx-theme>\n:root {", app(ThemeHead::class)->render());
    }

    #[Test]
    public function without_a_dark_scheme_the_css_is_the_one_root_rule_it_always_was(): void
    {
        $tokens = $this->tokensOf(['defaults' => ['color-bg' => '#ffffff', 'color-text' => '#111111']]);

        $this->assertSame([], $tokens->dark());
        $this->assertSame(":root {\n  --site-color-bg: #ffffff;\n  --site-color-text: #111111;\n}", $tokens->css());
    }

    #[Test]
    public function a_letter_keeps_the_light_values(): void
    {
        $this->assertSame('#F7F7F4', theme_token('color-bg'));
        $this->assertSame('#F7F7F4', theme_token('color-accent-contrast'), 'a reference resolves in the light scheme too');
    }

    #[Test]
    public function the_test_theme_reads_in_both_schemes(): void
    {
        $this->assertSame([], ContrastCheck::failures(app(ThemeChain::class)));
        $this->assertSame(['defaults', 'dark scheme'], array_keys(ContrastCheck::palettes(app(ThemeChain::class))));
    }

    #[Test]
    public function text_that_does_not_read_in_the_dark_is_named_with_its_scheme(): void
    {
        $chain = $this->chainOf([
            'defaults' => ['color-bg' => '#F7F7F4', 'color-surface' => '#F4F5F1', 'color-text' => '#121413', 'color-text-muted' => '#555A57'],
            'presets' => ['warm' => ['title' => 'Warm', 'tokens' => ['color-bg' => '#FFF8EE']]],
            'schemes' => ['dark' => ['color-bg' => '#0D0F0E', 'color-text' => '#ECEDE8', 'color-text-muted' => '#3A3D3B']],
        ]);

        $failures = ContrastCheck::failures($chain);

        $this->assertContains('dark scheme: color-text-muted on color-bg is 1.75:1, below 4.5:1', $failures);
        $this->assertContains('preset warm, dark scheme: color-text-muted on color-bg is 1.75:1, below 4.5:1', $failures);
        $this->assertSame([], array_filter($failures, static fn (string $line): bool => str_starts_with($line, 'defaults:')), 'the light palette reads');
        $this->assertContains(
            'dark scheme: color-text on color-surface is 1.07:1, below 4.5:1',
            $failures,
            'a surface without a dark value stays light under the dark text',
        );
    }

    #[Test]
    public function a_scheme_other_than_dark_is_an_error(): void
    {
        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage('unknown scheme "dim" under "schemes"; there is only "dark"');

        $this->tokensOf(['schemes' => ['dim' => ['color-bg' => '#000000']]])->values();
    }

    #[Test]
    public function a_dark_name_outside_the_vocabulary_or_of_the_wrong_type_is_an_error(): void
    {
        try {
            $this->tokensOf(['schemes' => ['dark' => ['color-brand' => '#000000']]])->values();
            $this->fail('An unknown name passed.');
        } catch (ThemeException $e) {
            $this->assertStringContainsString('schemes.dark sets "color-brand", which is not in the vocabulary', $e->getMessage());
        }

        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage('schemes.dark sets "radius-md" to "#000000", which is not a valid length');

        $this->tokensOf(['defaults' => ['radius-md' => '4px'], 'schemes' => ['dark' => ['radius-md' => '#000000']]])->values();
    }

    #[Test]
    public function the_check_says_the_dark_value_a_layer_left_behind_and_a_scheme_it_cannot_print(): void
    {
        $this->artisan('webx:theme:check')
            ->expectsOutputToContain('note: '.self::root().'/site recolours "color-border" in its defaults, but its dark value still comes from fixture/dark-parent')
            ->doesntExpectOutputToContain('"color-accent" in its defaults')
            ->assertSuccessful();

        $directory = $this->directory(['schemes' => ['night' => ['color-bg' => '#000000']]]);

        $this->artisan('webx:theme:check', ['path' => $directory])
            ->expectsOutputToContain('unknown scheme "night" under "schemes"')
            ->assertFailed();
    }

    #[Test]
    public function the_check_notes_text_that_does_not_read_in_the_dark(): void
    {
        $directory = $this->directory([
            'defaults' => ['color-bg' => '#F7F7F4', 'color-text' => '#121413'],
            'schemes' => ['dark' => ['color-bg' => '#0D0F0E', 'color-text' => '#202322']],
        ]);

        $this->artisan('webx:theme:check', ['path' => $directory])
            ->expectsOutputToContain('note: dark scheme: color-text on color-bg is')
            ->assertSuccessful();
    }

    #[Test]
    public function show_gives_each_token_its_dark_value_and_the_layer_of_it(): void
    {
        $this->artisan('webx:theme:show', ['--json' => true])->assertSuccessful();

        $this->artisan('webx:theme:show')
            ->expectsOutputToContain('Dark from')
            ->expectsOutputToContain('as light')
            ->assertSuccessful();

        $report = app(ThemeReport::class)->toArray();

        $this->assertSame('#9AA8FF', $report['tokens']['color-accent']['dark']);
        $this->assertSame(self::root().'/site', $report['tokens']['color-accent']['dark_from']);
        $this->assertSame(self::PARENT, $report['tokens']['color-bg']['dark_from']);
        $this->assertNull($report['tokens']['color-highlight']['dark'], 'no dark value: the dark scheme keeps the light one');
    }

    /**
     * The test theme, written once for the class: the package with both palettes and the local
     * theme over it.
     */
    private static function root(): string
    {
        if (self::$root !== null) {
            return self::$root;
        }

        $root = str_replace('\\', '/', sys_get_temp_dir()).'/webx-themes-dark-'.bin2hex(random_bytes(6));
        mkdir($root.'/parent', 0777, true);
        mkdir($root.'/site');

        file_put_contents($root.'/parent/composer.json', json_encode([
            'name' => self::PARENT,
            'type' => 'webx-theme',
            'extra' => ['webx' => ['theme' => ['title' => 'Dark parent']]],
        ]));

        file_put_contents($root.'/parent/tokens.json', json_encode([
            'defaults' => [
                'color-bg' => '#F7F7F4',
                'color-surface' => '#F4F5F1',
                'color-text' => '#121413',
                'color-text-muted' => '#555A57',
                'color-border' => '#C3C5BF',
                'color-accent' => '#2E49F5',
                'color-accent-contrast' => 'var(--site-color-bg)',
                'color-highlight' => '#FFE08A',
                'color-danger' => '#B3261E',
                'color-success' => '#1F8A5B',
            ],
            'schemes' => ['dark' => [
                'color-bg' => '#0D0F0E',
                'color-surface' => '#121413',
                'color-text' => '#ECEDE8',
                'color-text-muted' => '#989D99',
                'color-border' => '#2A2E2C',
                'color-accent' => '#8394FF',
                'color-accent-contrast' => '#0D0F0E',
                'color-danger' => '#F0765F',
                'color-success' => '#1F8A5B',
            ]],
        ], JSON_PRETTY_PRINT));

        file_put_contents($root.'/site/theme.json', json_encode(['theme' => ['title' => 'Test theme', 'uses' => [self::PARENT]]]));
        file_put_contents($root.'/site/tokens.json', json_encode([
            'defaults' => ['color-border' => '#B0B3AC'],
            'schemes' => ['dark' => ['color-accent' => '#9AA8FF']],
        ], JSON_PRETTY_PRINT));

        return self::$root = $root;
    }

    /**
     * A local theme in a temporary directory with the given tokens.json, standing on nothing.
     *
     * @param  array<string, mixed>  $tokens
     */
    private function directory(array $tokens): string
    {
        $directory = str_replace('\\', '/', sys_get_temp_dir()).'/webx-themes-'.bin2hex(random_bytes(6));
        $this->temporary[] = $directory;

        mkdir($directory);
        file_put_contents($directory.'/theme.json', json_encode(['theme' => ['title' => 'Temporary']]));
        file_put_contents($directory.'/tokens.json', json_encode($tokens));

        return $directory;
    }

    /** @param array<string, mixed> $tokens */
    private function chainOf(array $tokens): ThemeChain
    {
        return ThemeChain::resolve($this->directory($tokens), app(ThemeLocator::class));
    }

    /** @param array<string, mixed> $tokens */
    private function tokensOf(array $tokens): Tokens
    {
        return new Tokens($this->chainOf($tokens));
    }
}
