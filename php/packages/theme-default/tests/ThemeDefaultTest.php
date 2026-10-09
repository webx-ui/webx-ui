<?php

declare(strict_types=1);

namespace WebxUi\ThemeDefault\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeManifest;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Themes\Vocabulary;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The bottom of every site's chain. What the engine leaves to "the layer below" ends here, so
 * this theme owes a value to every token of the vocabulary — and since it is installed from
 * Composer with its dist/ already built, it also owes a dist/ that matches its src/.
 */
class ThemeDefaultTest extends TestCase
{
    private const string NAME = 'webx-ui/theme-default';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        // The widgets too: the header is `<x-webx-header>`, and the theme requires the package.
        return [ThemeServiceProvider::class, WidgetsServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.name', 'Harbor Logistics');
        $app['config']->set('webx-themes.theme', self::NAME);

        // The site's own views: a header and a footer of its own, the way a site overrides the theme's.
        $app['config']->set('view.paths', [str_replace('\\', '/', __DIR__).'/Fixtures/site']);

        // Registered rather than left to Composer: the test must not depend on the theme being
        // installed into whatever vendor/ runs it.
        $app->resolving(ThemeLocator::class, function (ThemeLocator $locator): void {
            $locator->register(self::NAME, self::path());
        });
    }

    #[Test]
    public function every_token_of_the_vocabulary_has_a_value(): void
    {
        $defaults = $this->manifest()->tokens()->defaults;
        $vocabulary = Vocabulary::base();

        $this->assertEqualsCanonicalizing($vocabulary->names(), array_keys($defaults));

        foreach ($defaults as $name => $value) {
            $this->assertTrue($vocabulary->accepts($name, $value), "{$name}: \"{$value}\" is not a {$vocabulary->type($name)?->value}.");
        }
    }

    #[Test]
    public function presets_name_only_tokens_of_the_vocabulary(): void
    {
        $presets = $this->manifest()->tokens()->presets;
        $vocabulary = Vocabulary::base();

        $this->assertNotEmpty($presets);

        foreach ($presets as $preset => ['title' => $title, 'tokens' => $tokens]) {
            $this->assertNotSame('', $title, "Preset {$preset} has no title.");

            foreach ($tokens as $name => $value) {
                $this->assertTrue($vocabulary->has($name), "Preset {$preset}: \"{$name}\" is not in the vocabulary.");
                $this->assertTrue($vocabulary->accepts($name, $value), "Preset {$preset}: {$name} \"{$value}\" is not a {$vocabulary->type($name)?->value}.");
            }
        }
    }

    /** WCAG AA for body text, on the defaults and on every preset merged over them. */
    #[Test]
    public function text_reads_in_every_preset(): void
    {
        $file = $this->manifest()->tokens();
        $palettes = ['defaults' => $file->defaults];

        foreach ($file->presets as $preset => ['tokens' => $tokens]) {
            $palettes[$preset] = array_replace($file->defaults, $tokens);
        }

        foreach ($palettes as $palette => $values) {
            foreach ([['color-text', 'color-bg'], ['color-text', 'color-surface'], ['color-text-muted', 'color-bg'],
                ['color-text-muted', 'color-surface'], ['color-accent-contrast', 'color-accent'], ['color-accent', 'color-bg']] as [$fore, $back]) {
                $ratio = self::contrast(self::resolve($values, $fore), self::resolve($values, $back));

                $this->assertGreaterThanOrEqual(4.5, $ratio, sprintf('%s: %s on %s is %.2f:1.', $palette, $fore, $back, $ratio));
            }
        }
    }

    /** dist/ is committed; an edit to src/ without a build would ship yesterday's CSS. */
    /**
     * The showcase pages are seeded by module-pages, and their blocks by the block types of the
     * module-blocks demo — the only types a fresh site has until the theme brings its own (TH2).
     * A type outside those three is a page the seed writes and the site cannot print.
     */
    #[Test]
    public function the_showcase_pages_use_only_the_demo_block_types(): void
    {
        $pages = glob(self::path().'/demo/pages/*.json') ?: [];
        $this->assertNotSame([], $pages);

        $keys = [];
        $walk = function (array $page, string $file) use (&$walk, &$keys): void {
            $this->assertIsString($page['title'] ?? null, $file);
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', (string) ($page['slug'] ?? ''), $file);

            $blocks = $page['blocks'] ?? [];
            while ($blocks !== []) {
                $block = array_shift($blocks);
                $this->assertContains($block['type'] ?? null, ['hero', 'text', 'columns'], $file);
                $this->assertNotContains($block['key'], $keys, "{$file}: block keys are unique");
                $keys[] = $block['key'];
                array_push($blocks, ...($block['values']['items'] ?? []));
            }

            foreach ($page['children'] ?? [] as $child) {
                $walk($child, $file);
            }
        };

        foreach ($pages as $file) {
            $page = json_decode((string) file_get_contents($file), true);
            $this->assertIsArray($page, $file);
            $walk($page, basename($file));
        }
    }

    #[Test]
    public function dist_is_built_from_the_current_sources(): void
    {
        $this->assertFileExists(self::path().'/dist/theme.css');

        $built = json_decode((string) file_get_contents(self::path().'/dist/sources.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($built);

        $this->assertSame(self::sources(), $built['files'] ?? null, 'src/ changed since dist/ was built: run `npx vite build` in php/packages/theme-default and commit dist/.');
    }

    /** The part of the token rule (spec §8) a stylesheet can break without anyone noticing. */
    #[Test]
    public function the_stylesheets_know_only_site_tokens(): void
    {
        $vocabulary = Vocabulary::base();

        foreach (array_keys(self::sources()) as $relative) {
            if (! str_ends_with($relative, '.css')) {
                continue;
            }

            $css = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(self::path().'/'.$relative));

            preg_match_all('/var\(\s*--([a-z0-9-]+)\s*([,)])/i', $css, $references, PREG_SET_ORDER);

            foreach ($references as [, $variable, $after]) {
                $this->assertStringStartsWith('site-', $variable, "{$relative}: --{$variable} is not a site token.");
                $this->assertTrue($vocabulary->has(substr($variable, 5)), "{$relative}: --{$variable} is not in the vocabulary.");
                $this->assertSame(')', $after, "{$relative}: var(--{$variable}, …) has a fallback that no preset repaints.");
            }

            $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\(/i', $css, "{$relative}: a literal colour.");
            $this->assertDoesNotMatchRegularExpression('/@media[^{]*width/i', $css, "{$relative}: width is the container's to decide (@container).");
        }
    }

    #[Test]
    public function the_layout_prints_the_theme_the_head_and_both_regions(): void
    {
        $this->stubModules();

        $html = Blade::render('<x-layout><p>Body of the page</p></x-layout>');

        $this->assertStringContainsString('<style data-webx-theme>', $html);
        $this->assertStringContainsString('--site-color-accent: #1f5fbf;', $html);
        $this->assertStringContainsString('<!-- seo:null -->', $html);
        $this->assertStringContainsString('<!-- blocks -->', $html);
        $this->assertStringContainsString('<main id="content" class="site-main"><p>Body of the page</p></main>', $html);

        // The regions' fallbacks are looked up through the chain, so the site's own files win.
        $this->assertStringContainsString("<header class=\"site-header\">The site's own header</header>", $html);
        $this->assertStringContainsString("<footer class=\"site-footer\">The site's own footer</footer>", $html);
    }

    /**
     * The theme's header and footer are rendered on the starter site, not here: they call
     * `menu()` and the pages model, which are autoloaded wherever those modules are installed —
     * the monorepo included — and need the modules booted with their tables. What a test can
     * hold without them is that both compile to valid PHP.
     */
    #[Test]
    public function the_shell_views_compile(): void
    {
        $this->stubModules();

        foreach (['header', 'footer', 'layout'] as $view) {
            $compiled = Blade::compileString((string) file_get_contents(self::path()."/views/components/{$view}.blade.php"));

            $this->assertNotEmpty(token_get_all($compiled, TOKEN_PARSE), "{$view} does not compile.");
        }
    }

    /** A module's view hands its own @webxSeo and @webxBlocks in the head slot; neither prints twice. */
    #[Test]
    public function a_head_from_the_page_replaces_the_layouts_own(): void
    {
        $this->stubModules();

        $html = Blade::render('<x-layout><x-slot:head><title>From the page</title></x-slot:head> Body</x-layout>');

        $this->assertStringContainsString('<title>From the page</title>', $html);
        $this->assertStringNotContainsString('<!-- seo', $html);
        $this->assertStringNotContainsString('<!-- blocks -->', $html);
    }

    #[Test]
    public function the_layout_is_found_through_the_chain(): void
    {
        $this->assertSame(self::path().'/views/components/layout.blade.php', str_replace('\\', '/', view()->getFinder()->find('components.layout')));
    }

    /** What module-seo and module-blocks would print, so the layout compiles without them. */
    private function stubModules(): void
    {
        Blade::directive('webxSeo', static fn (string $expression): string => sprintf('<?php echo "<!-- seo:%s -->"; ?>', trim($expression) === '' ? 'null' : 'subject'));
        Blade::directive('webxBlocks', static fn (): string => '<?php echo "<!-- blocks -->"; ?>');
        Blade::anonymousComponentPath(str_replace('\\', '/', __DIR__).'/Fixtures/webx-blocks', 'webx-blocks');

        // Compiled views outlive a run; a layout compiled with yesterday's stubs would hide a change.
        $this->artisan('view:clear');
    }

    private function manifest(): ThemeManifest
    {
        return ThemeManifest::fromPackage(self::path());
    }

    /**
     * Every file under src/ by sha256, line endings normalised — the walk vite.config.js does.
     *
     * @return array<string, string>
     */
    private static function sources(): array
    {
        $files = [];
        $root = self::path();

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src', RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);
            $files[$relative] = hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($file->getPathname())));
        }

        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * A token's value with `var(--site-…)` references followed.
     *
     * @param  array<string, string>  $values
     */
    private static function resolve(array $values, string $name): string
    {
        $value = $values[$name];

        for ($depth = 0; $depth < 10 && preg_match('/^var\(--site-([a-z0-9-]+)\)$/', $value, $match) === 1; $depth++) {
            $value = $values[$match[1]];
        }

        return $value;
    }

    private static function contrast(string $fore, string $back): float
    {
        [$lighter, $darker] = [self::luminance($fore), self::luminance($back)];

        if ($lighter < $darker) {
            [$lighter, $darker] = [$darker, $lighter];
        }

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function luminance(string $hex): float
    {
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $hex, 'The contrast check reads six-digit hex colours only.');

        $channels = array_map(static function (string $pair): float {
            $channel = hexdec($pair) / 255;

            return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function path(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }
}
