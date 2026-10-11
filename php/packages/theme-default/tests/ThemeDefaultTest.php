<?php

declare(strict_types=1);

namespace WebxUi\ThemeDefault\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebxUi\Themes\Checks\CssCheck;
use WebxUi\Themes\Checks\CssFinding;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeManifest;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Themes\Vocabulary;
use WebxUi\Widgets\Widgets;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The bottom of every site's chain. What the engine leaves to "the layer below" ends here, so
 * this theme owes a value to every token of the vocabulary — and since it is installed from
 * Composer with its dist/ already built, it also owes a dist/ that matches its src/.
 */
class ThemeDefaultTest extends TestCase
{
    private const string NAME = 'webx-ui/theme-default';

    /** The languages of the starter site, which the showcase is written in. */
    private const array LANGUAGES = ['en', 'ru', 'pl'];

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
     * The showcase pages are seeded by module-pages, and their blocks by the six blocks of the
     * theme (synced before the demo), the `columns` of the module-blocks demo — its `hero` and
     * `text` yield to the theme's —, the theme's own `demo/blocks/` (THEMES §15.1) and the blocks
     * the widgets offer (WIDGETS §15.1), which the blocks demo installs for the pages that stand
     * on them. Any other type is a page the seed writes and the site cannot print.
     *
     * A picture is named `demo:<file>` and the media demo stores `demo/media/<file>.*` under that
     * name; a name with no file is a hole in a gallery.
     */
    #[Test]
    public function the_showcase_pages_use_only_the_demo_block_types(): void
    {
        $pages = glob(self::path().'/demo/pages/*.json') ?: [];
        $this->assertNotSame([], $pages);

        $types = ['columns', ...array_map(
            static fn (string $file): string => basename($file, '.json'),
            [...glob(self::path().'/blocks/*.json') ?: [], ...glob(self::path().'/demo/blocks/*.json') ?: [], ...glob(Widgets::path().'/resources/blocks/*.json') ?: []],
        )];
        $pictures = array_map(static fn (string $file): string => 'demo:'.pathinfo($file, PATHINFO_FILENAME), glob(self::path().'/demo/media/*') ?: []);
        $named = [];

        foreach ($pages as $file) {
            preg_match_all('/"path":\s*"(demo:[^"]+)"/', (string) file_get_contents($file), $found);
            array_push($named, ...$found[1]);
        }

        $this->assertNotSame([], $named);
        $this->assertSame([], array_values(array_diff($named, $pictures)), 'pictures named with no file in demo/media');

        $keys = [];
        $walk = function (array $page, string $file) use (&$walk, &$keys, $types): void {
            // In the three languages of the starter site, every page but the one left in English on
            // purpose: the language switcher has a translation to lead to (§13 of the widgets spec).
            $languages = ($page['slug'] ?? null) === 'english-only' ? null : self::LANGUAGES;

            foreach (['title', 'slug'] as $field) {
                $value = $page[$field] ?? null;
                $this->assertSame($languages, is_array($value) ? array_keys($value) : null, "{$file}: {$field} of ".json_encode($value));

                foreach ((array) $value as $one) {
                    $this->assertIsString($one, $file);
                    $this->assertNotSame('', $one, $file);
                }
            }

            foreach ((array) $page['slug'] as $slug) {
                $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug, $file);
            }

            $blocks = $page['blocks'] ?? [];
            while ($blocks !== []) {
                $block = array_shift($blocks);
                $this->assertContains($block['type'] ?? null, $types, $file);
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

    /**
     * Every word a visitor reads on a translated showcase page is there in all three languages:
     * a text left as one string would be English under the Russian and the Polish address, and
     * nothing else would say so. Code-like values — a ratio, a width, an address — stay strings.
     */
    #[Test]
    public function the_showcase_is_written_in_three_languages(): void
    {
        $words = ['heading', 'note', 'body', 'title', 'subtitle', 'text', 'button_label', 'caption', 'ended', 'before_label', 'after_label', 'link_label', 'label', 'alt'];

        $walk = function (mixed $value, string $at, bool $translated) use (&$walk, $words): void {
            if (! is_array($value)) {
                return;
            }

            foreach ($value as $key => $one) {
                $here = "{$at}.{$key}";

                if (is_array($one) && array_key_exists('en', $one)) {
                    $this->assertSame(self::LANGUAGES, array_keys($one), $here);
                    $this->assertNotContains('', $one, $here);
                } elseif ($translated && is_string($one) && in_array($key, $words, true) && preg_match('/\p{L}/u', $one) === 1) {
                    $this->fail("{$here} is in English only: \"{$one}\"");
                } else {
                    $walk($one, $here, $translated && $key !== 'children');
                }
            }
        };

        foreach (glob(self::path().'/demo/pages/*.json') ?: [] as $file) {
            $page = json_decode((string) file_get_contents($file), true);
            $pages = [$page];

            while ($pages !== []) {
                $one = array_shift($pages);
                array_push($pages, ...($one['children'] ?? []));
                unset($one['children']);
                $walk($one, basename($file).':'.json_encode($one['slug']), is_array($one['slug']));
            }
        }
    }

    /**
     * The showcase's own block types — a widget needs a block to stand in on a page — are what
     * `webx:blocks:export` writes, named after their slug, and know only the site's tokens.
     */
    #[Test]
    public function the_showcase_block_types_are_block_documents(): void
    {
        foreach (glob(self::path().'/demo/blocks/*.json') ?: [] as $file) {
            $type = json_decode((string) file_get_contents($file), true);
            $slug = basename($file, '.json');

            $this->assertIsArray($type, $file);
            $this->assertSame($slug, $type['slug'] ?? null, $file);
            $this->assertStringContainsString("data-wx-block=\"{$slug}\"", (string) ($type['template'] ?? ''), $file);
            $this->assertSame([], self::findings((string) ($type['styles'] ?? ''), $file));
        }
    }

    /**
     * The six blocks of THEMES §9.1, in the format `webx:blocks:export` writes, that sync puts on
     * every site with this theme: §8 — the check that refuses a type at sync —, the width from
     * the container, every selector under the block, every word a visitor reads a translatable
     * field (rule 18) and every fixed label a word of this theme.
     */
    #[Test]
    public function the_six_blocks_are_block_documents_on_the_site_tokens(): void
    {
        $files = glob(self::path().'/blocks/*.json') ?: [];
        $this->assertSame(['cards', 'contact', 'cta', 'hero', 'media-text', 'text'], array_map(static fn (string $file): string => basename($file, '.json'), $files));

        $words = Arr::dot(require self::path().'/lang/en/theme.php');

        foreach ($files as $file) {
            $type = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $slug = basename($file, '.json');
            $styles = (string) ($type['styles'] ?? '');
            $template = (string) ($type['template'] ?? '');

            $this->assertIsArray($type, $file);
            $this->assertSame($slug, $type['slug'] ?? null, $file);
            $this->assertStringContainsString("data-wx-block=\"{$slug}\"", $template, $file);
            $this->assertSame([], self::findings($styles, "blocks/{$slug}.json"));
            $this->assertStringNotContainsString('@media', $styles, $file);
            $this->assertStringNotContainsString('!important', $styles, $file);

            // Every rule's selectors, outside the at-rules' own lines, start with the block's class.
            foreach (explode('}', (string) preg_replace('#/\*.*?\*/#s', '', $styles)) as $rule) {
                if (! str_contains($rule, '{')) {
                    continue;
                }

                // What stands before the rule's brace, after an `@container (…) {` that opens it.
                $head = substr($rule, 0, (int) strrpos($rule, '{'));
                $selector = trim(substr($head, str_contains($head, '{') ? (int) strrpos($head, '{') + 1 : 0));

                foreach ($selector === '' ? [] : explode(',', $selector) as $one) {
                    $this->assertStringStartsWith(".b-{$slug}", trim($one), "{$file}: \"{$one}\" is outside the block");
                }
            }

            foreach (self::fieldsOf($type['schema'] ?? []) as $id => $field) {
                $visible = in_array($field['type'] ?? null, ['wx-input', 'wx-textarea', 'wx-rich-text'], true)
                    && ! str_ends_with($id, '_url') && $id !== 'form';
                $this->assertSame($visible, ($field['localized'] ?? false) === true, "{$file}: {$id} is ".($visible ? 'read by a visitor' : 'not words'));
            }

            preg_match_all("/__\\('theme::([a-z0-9_-]+)\\.([a-z0-9_.-]+)'\\)/", $template, $labels, PREG_SET_ORDER);

            foreach ($labels as [, $group, $key]) {
                $this->assertSame('theme', $group, "{$file}: the words of the theme are lang/<locale>/theme.php");
                $this->assertArrayHasKey($key, $words, "{$file}: theme::{$group}.{$key}");
            }
        }
    }

    /** What sync refuses a type for is what `webx:theme:check` lists — and here it lists nothing. */
    #[Test]
    public function the_check_of_the_engine_passes_on_the_theme(): void
    {
        $this->artisan('webx:theme:check', ['path' => self::path()])
            ->expectsOutputToContain('6 block types')
            ->assertSuccessful();
    }

    /**
     * Key → node of every field, the fields of a repeater under their own ids.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function fieldsOf(mixed $nodes): array
    {
        $fields = [];

        foreach (is_array($nodes) ? $nodes : [] as $node) {
            if (is_array($node) && is_string($node['id'] ?? null)) {
                $fields[$node['id']] = $node;
                $fields += self::fieldsOf($node['children'] ?? []);
            }
        }

        return $fields;
    }

    /** @return list<string> */
    private static function findings(string $css, string $file): array
    {
        return array_map(
            static fn (CssFinding $finding): string => "{$file}:{$finding->line} {$finding->message()}",
            CssCheck::check($css, Vocabulary::base()),
        );
    }

    #[Test]
    public function dist_is_built_from_the_current_sources(): void
    {
        $this->assertFileExists(self::path().'/dist/theme.css');

        $built = json_decode((string) file_get_contents(self::path().'/dist/sources.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($built);

        $this->assertSame(self::sources(), $built['files'] ?? null, 'src/ changed since dist/ was built: run `npx vite build` in php/packages/theme-default and commit dist/.');
    }

    /** The token rule (spec §8) — the engine's check, the one `webx:theme:check` runs. */
    #[Test]
    public function the_stylesheets_know_only_site_tokens(): void
    {
        foreach (array_keys(self::sources()) as $relative) {
            if (str_ends_with($relative, '.css')) {
                $this->assertSame([], self::findings((string) file_get_contents(self::path().'/'.$relative), $relative));
            }
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

        foreach (['components/header', 'components/footer', 'components/layout', 'errors/404'] as $view) {
            $compiled = Blade::compileString((string) file_get_contents(self::path()."/views/{$view}.blade.php"));

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

    /** A missing address in the site's frame (THEMES §4): the header, the head of an error, the words in the request's language. */
    #[Test]
    public function a_missing_address_gets_the_404_page_in_the_shell(): void
    {
        $this->stubModules();
        $this->app->setLocale('pl');

        $html = (string) $this->get('/no-such-page')->assertNotFound()->getContent();

        $this->assertStringContainsString('<html lang="pl">', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $html);
        $this->assertStringContainsString('<title>Nie znaleziono strony — Harbor Logistics</title>', $html);
        $this->assertStringContainsString('<!-- blocks -->', $html);
        $this->assertStringNotContainsString('<!-- seo', $html);
        $this->assertStringContainsString("<header class=\"site-header\">The site's own header</header>", $html);
        $this->assertStringContainsString('<h1>Nie znaleziono strony</h1>', $html);
    }

    /** The words of the layout: the same keys in every language of the panel. */
    #[Test]
    public function the_words_have_the_same_keys_in_every_language(): void
    {
        $keys = static fn (array $lines): array => array_keys(Arr::dot($lines));
        $english = $keys(require self::path().'/lang/en/theme.php');
        $languages = array_map('basename', glob(self::path().'/lang/*', GLOB_ONLYDIR) ?: []);

        $this->assertEqualsCanonicalizing(['de', 'en', 'es', 'fr', 'it', 'pl', 'pt', 'ru', 'tr', 'uk'], $languages);

        foreach ($languages as $code) {
            $this->assertSame($english, $keys(require self::path()."/lang/{$code}/theme.php"), "lang/{$code}/theme.php");
        }
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
