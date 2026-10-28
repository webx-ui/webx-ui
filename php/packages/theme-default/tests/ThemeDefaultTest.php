<?php

declare(strict_types=1);

namespace WebxUi\ThemeDefault\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebxUi\Compositions\Compiler;
use WebxUi\Compositions\Compositions;
use WebxUi\Compositions\Node;
use WebxUi\Compositions\Testing\IsolatedCompositions;
use WebxUi\Themes\Checks\CssCheck;
use WebxUi\Themes\Checks\CssFinding;
use WebxUi\Themes\Checks\FontCheck;
use WebxUi\Themes\Checks\IconCheck;
use WebxUi\Themes\Checks\LineCheck;
use WebxUi\Themes\Checks\MailCheck;
use WebxUi\Themes\Testing\ThemeContract;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeFonts;
use WebxUi\Themes\ThemeIcons;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeManifest;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Themes\ThemeSources;
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
    // A compiled set of compositions a test: they load other modules into the layout.
    use IsolatedCompositions;

    // The contract every theme's test runs (THEMES §18) — on the theme every site stands on first.
    use ThemeContract;

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
        $this->isolateCompositions($app);

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
            // The columns of module-blocks: a longer one stops webx:demo halfway, at the insert.
            $this->assertLessThanOrEqual(255, mb_strlen((string) ($type['description'] ?? '')), "{$file}: the description is over 255 characters");
            $this->assertLessThanOrEqual(255, mb_strlen((string) ($type['title'] ?? '')), "{$file}: the title is over 255 characters");
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
        $code = Artisan::call('webx:theme:check', ['path' => self::path()]);
        // The words wrapped at the width of the terminal, joined back.
        $said = (string) preg_replace('/\s+/', ' ', Artisan::output());

        $this->assertSame(0, $code, $said);
        $this->assertStringContainsString('6 block types', $said);
        $this->assertStringContainsString('27 icons, every one its templates name', $said);
        $this->assertMatchesRegularExpression('/\d+ letters, every colour from theme_token\(\)/', $said);
    }

    /**
     * A header and a footer of the site's own (the fixtures in Fixtures/site) are copies of the
     * dispatchers of the compositions `header` and `footer`: they print, and the check says what
     * the patches of the themes do not reach (COMPOSITIONS §6.4).
     */
    #[Test]
    public function the_check_notes_a_header_and_a_footer_the_site_copied(): void
    {
        Artisan::call('webx:theme:check');
        $said = (string) preg_replace('/\s+/', ' ', Artisan::output());

        foreach (['header', 'footer'] as $part) {
            $this->assertStringContainsString("Fixtures/site/components/{$part}.blade.php note: overrides the composition {$part}", $said);
            $this->assertStringContainsString("<x-webx-composition::place composition=\"{$part}\" id=\"…\" only=\"added\" />", $said);
        }
    }

    /**
     * The frame of the letters (§7.5): every value a token's, taken on the server — no var(), no
     * colour of its own — and the line below in the language of the letter.
     */
    #[Test]
    public function the_letters_stand_in_a_frame_painted_with_the_values_of_the_tokens(): void
    {
        $this->app->setLocale('pl');

        $html = View::file(self::path().'/views/mail/layout.blade.php', ['title' => 'A letter', 'slot' => new HtmlString('<p>Body of the letter</p>')])->render();

        $this->assertStringContainsString('<html lang="pl">', $html);
        $this->assertStringContainsString('<body style="margin:0;padding:0;background-color:#f6f7f9;">', $html);
        $this->assertStringContainsString('background-color:#ffffff;border:1px solid #dde1e6;border-radius:8px;font-family:Inter, system-ui, -apple-system,&quot;Segoe UI&quot;', $html);
        $this->assertStringContainsString('style="color:#1f5fbf;text-decoration:none;">Harbor Logistics</a>', $html);
        $this->assertStringContainsString('<p>Body of the letter</p>', $html);
        $this->assertStringContainsString('Wysłano ze strony Harbor Logistics', $html);
        $this->assertStringNotContainsString('var(', $html);
        $this->assertSame([], MailCheck::check((string) file_get_contents(self::path().'/views/mail/layout.blade.php')));

        // A token that refers to another comes with its value: a letter has no variables to follow.
        $this->assertSame(theme_token('font-body'), theme_token('font-heading'));
    }

    /** The check reads the layer's templates; the showcase's own block types are not a layer's, so they are read here. */
    #[Test]
    public function the_showcase_names_only_icons_the_theme_has(): void
    {
        $names = array_keys(ThemeIcons::files($this->manifest()));
        $this->assertContains('arrow-right', $names);

        foreach (glob(self::path().'/demo/blocks/*.json') ?: [] as $file) {
            $document = json_decode((string) file_get_contents($file), true);
            $template = is_array($document) && is_string($document['template'] ?? null) ? $document['template'] : '';

            $this->assertSame([], IconCheck::check($template, $names), basename($file));
        }
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

    /** The token rule (spec §8) — the engine's check, the one `webx:theme:check` runs. */
    #[Test]
    public function the_stylesheets_know_only_site_tokens(): void
    {
        foreach (array_keys(ThemeSources::of(self::path())) as $relative) {
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
        $this->assertMatchesRegularExpression('~<main id="content" class="site-main">\s*<p>Body of the page</p>\s*</main>~', $html);

        // The regions' fallbacks are looked up through the chain, so the site's own files win.
        $this->assertStringContainsString("<header class=\"site-header\">The site's own header</header>", $html);
        $this->assertStringContainsString("<footer class=\"site-footer\">The site's own footer</footer>", $html);
    }

    /** THEMES §23, TH3.4: module-seo's FAQ, then its interlinking, close the page's column. */
    #[Test]
    public function the_layout_closes_the_page_with_its_faq_and_its_links(): void
    {
        $this->stubModules();
        $fixtures = str_replace('\\', '/', __DIR__).'/Fixtures/webx-seo';
        View::addNamespace('webx-seo', $fixtures);
        Blade::anonymousComponentPath($fixtures, 'webx-seo');
        Compositions::load(dirname(self::path()).'/module-seo/resources/compositions');

        $html = Blade::render('<x-layout><p>Body of the page</p></x-layout>');

        $this->assertMatchesRegularExpression(
            '~<main id="content" class="site-main">\s*<p>Body of the page</p>\s*<section class="webx-faq">FAQ of the page</section>\s*<nav class="webx-links">Links of the page</nav>\s*</main>~',
            $html,
        );
    }

    /**
     * The header and the footer are rendered with their modules booted in
     * HeaderFooterCompositionTest: they call `menu()` and the pages model, which need their tables.
     * What this test holds is that the dispatchers and every leaf compile to valid PHP.
     */
    #[Test]
    public function the_shell_views_compile(): void
    {
        $this->stubModules();

        $leaves = array_map(static fn (string $file): string => substr($file, strlen(self::path().'/views/'), -strlen('.blade.php')), glob(self::path().'/views/components/{header,footer}/*.blade.php', GLOB_BRACE) ?: []);

        $this->assertCount(10, $leaves);

        foreach (['components/header', 'components/footer', 'components/layout', 'errors/404', ...$leaves] as $view) {
            $compiled = Blade::compileString((string) file_get_contents(self::path()."/views/{$view}.blade.php"));

            $this->assertNotEmpty(token_get_all($compiled, TOKEN_PARSE), "{$view} does not compile.");
        }
    }

    /**
     * The three places of module-snippets (SNIPPETS §11.1): without the module the page is what it
     * always was — viewport, then the theme; with it, a place above the theme, one right after
     * <body> and one closing it.
     */
    #[Test]
    public function the_places_of_the_snippets_are_there_only_with_the_module(): void
    {
        $this->stubModules();

        $html = Blade::render('<x-layout><p>Body</p></x-layout>');

        $this->assertStringNotContainsString('place:', $html);
        $this->assertMatchesRegularExpression('~<meta name="viewport" content="width=device-width, initial-scale=1">\s*<style data-webx-theme>~', $html);
        $this->assertMatchesRegularExpression('~<body class="site">\s*<a class="site-skip"~', $html);

        // A stand-in for the module: its view, and a component by its name.
        $fixtures = str_replace('\\', '/', __DIR__).'/Fixtures/webx-snippets';
        View::addNamespace('webx-snippets', $fixtures);
        Blade::anonymousComponentPath($fixtures.'/components');
        Compositions::load(dirname(self::path()).'/module-snippets/resources/compositions');

        // The module adds its places itself (COMPOSITIONS §5.3): another set of layers is another
        // compiled layout, on the next request.
        $this->app->forgetScopedInstances();
        $html = Blade::render('<x-layout><p>Body</p></x-layout>');

        $this->assertMatchesRegularExpression('~<meta name="viewport" content="width=device-width, initial-scale=1">\s*<!-- place:head -->\s*<style data-webx-theme>~', $html);
        $this->assertMatchesRegularExpression('~<body class="site">\s*<!-- place:body_start -->\s*<a class="site-skip"~', $html);
        $this->assertMatchesRegularExpression('~<!-- place:body_end -->\s*</body>~', $html);
    }

    /**
     * The pieces of the modules come from the modules (COMPOSITIONS §5.3): the quick contact of
     * widgets after the footer, the FAQ and the links of module-seo after the page, the places of
     * module-snippets — and the compiled layout asks nothing on a request.
     */
    #[Test]
    public function the_modules_add_their_pieces_and_the_layout_names_none_of_them(): void
    {
        $this->stubModules();
        $fixtures = str_replace('\\', '/', __DIR__).'/Fixtures';
        View::addNamespace('webx-seo', $fixtures.'/webx-seo');
        Blade::anonymousComponentPath($fixtures.'/webx-seo', 'webx-seo');
        View::addNamespace('webx-snippets', $fixtures.'/webx-snippets');
        Blade::anonymousComponentPath($fixtures.'/webx-snippets/components');
        Compositions::load(dirname(self::path()).'/module-seo/resources/compositions');
        Compositions::load(dirname(self::path()).'/module-snippets/resources/compositions');

        $tree = app(Compiler::class)->tree('layout');
        $ids = static fn (string $id): array => array_map(static fn (Node $node): string => $node->id, $tree->find($id)->children ?? []);

        $this->assertSame(['snippets.body-start', 'skip-link', 'notice-bar', 'header', 'main', 'footer', 'widgets.contact-button', 'scripts.stack', 'snippets.body-end'], $ids('body'));
        $this->assertSame(['content', 'seo.faq', 'seo.links'], $ids('main'));
        $this->assertSame([], $tree->findings);

        $source = (string) file_get_contents(self::path().'/compositions/layout/default.json');

        foreach (['seo', 'snippets', 'contact-button'] as $module) {
            $this->assertStringNotContainsString($module, $source, 'the base of the layout knows no module');
        }

        Blade::render('<x-layout><p>Body</p></x-layout>');
        $compiled = (string) file_get_contents(View::getFinder()->find(app(Compositions::class)->view('layout')));

        $this->assertStringContainsString('<x-webx-contact-button />', $compiled);
        $this->assertStringContainsString('<x-webx-seo::faq />', $compiled);
        $this->assertStringContainsString('<x-webx-snippets place="body_end" />', $compiled);
        $this->assertStringNotContainsString('view()->exists', $compiled);
        $this->assertStringNotContainsString('dynamic-component', $compiled);
    }

    /**
     * The example of the spec (COMPOSITIONS §10.1) on the real layout: the studio's smooth scroll,
     * no quick contact, the announcement under the header on every page — and on an article a class,
     * a progress bar after the header and no links. Patches of the site's config, the layer a theme's
     * files sit just under.
     */
    #[Test]
    public function a_site_patches_the_layout_by_id_on_default_and_on_a_page_type(): void
    {
        $this->stubModules();
        $fixtures = str_replace('\\', '/', __DIR__).'/Fixtures';
        View::addNamespace('webx-seo', $fixtures.'/webx-seo');
        Blade::anonymousComponentPath($fixtures.'/webx-seo', 'webx-seo');
        Compositions::load(dirname(self::path()).'/module-seo/resources/compositions');
        config()->set('webx-compositions.patches.layout', [
            'default' => [
                'attrs' => ['html' => ['data-webx-smooth-scroll' => true]],
                'remove' => ['widgets.contact-button'],
                'move' => [['id' => 'notice-bar', 'after' => 'header']],
            ],
            'blog.article' => [
                'attrs' => ['body' => ['class' => 'is-article']],
                'add' => [['id' => 'reading-progress', 'to' => 'body', 'after' => 'header', 'view' => 'components.reading-progress']],
                'remove' => ['seo.links'],
            ],
        ]);

        $page = Blade::render('<x-layout><p>Body</p></x-layout>');
        $article = Blade::render('<x-layout handle="blog.article"><p>Article</p></x-layout>');

        $this->assertStringStartsWith("<!doctype html>\n<html lang=\"en\" data-webx-smooth-scroll>", $page);
        $this->assertStringContainsString('<body class="site">', $page);
        $this->assertStringContainsString('Links of the page', $page);
        $this->assertStringNotContainsString('site-progress', $page);

        $this->assertStringStartsWith("<!doctype html>\n<html lang=\"en\" data-webx-smooth-scroll>", $article);
        $this->assertStringContainsString('<body class="site is-article">', $article);
        $this->assertMatchesRegularExpression('~site-header.*</header>\s*<div class="site-progress"></div>\s*<main~s', $article);
        $this->assertStringContainsString('FAQ of the page', $article);
        $this->assertStringNotContainsString('Links of the page', $article);

        $order = app(Compiler::class)->tree('layout', 'blog.article')->find('body')->children ?? [];
        $this->assertSame(['skip-link', 'header', 'reading-progress', 'notice-bar', 'main', 'footer', 'scripts.stack'], array_map(static fn (Node $node): string => $node->id, $order));
        // layout.minimal takes the quick contact off once more: said as a note, not as a finding.
        $this->assertSame([], array_values(array_filter(app(Compositions::class)->findings(), static fn ($finding): bool => ! $finding->note)));
    }

    /**
     * The other shells (COMPOSITIONS §3.7): `layout.minimal` is the layout with the brand for a
     * header and no footer, the pieces of the modules with it; `layout.blank` is a base of its own,
     * which only a module that belongs on every document adds to.
     */
    #[Test]
    public function the_minimal_and_the_blank_layout_stand_on_their_trees(): void
    {
        $this->stubModules();
        Compositions::load(dirname(self::path()).'/module-snippets/resources/compositions');
        View::addNamespace('webx-snippets', str_replace('\\', '/', __DIR__).'/Fixtures/webx-snippets');
        Blade::anonymousComponentPath(str_replace('\\', '/', __DIR__).'/Fixtures/webx-snippets/components');

        $minimal = Blade::render('<x-layout.minimal><p>Checkout</p></x-layout.minimal>');

        $this->assertStringContainsString('<a class="site-skip" href="#content">', $minimal);
        $this->assertStringContainsString('<a class="site-header__title" href="http://localhost">Harbor Logistics</a>', $minimal);
        $this->assertStringNotContainsString("The site's own header", $minimal);
        $this->assertStringNotContainsString('site-footer', $minimal);
        $this->assertStringContainsString('<!-- place:body_end -->', $minimal);

        $blank = Blade::render('<x-layout.blank><p>Promo</p></x-layout.blank>');

        $this->assertMatchesRegularExpression('~<body class="site site--blank">\s*<!-- place:body_start -->\s*<main id="content" class="site-main">\s*<p>Promo</p>\s*</main>~', $blank);
        $this->assertStringNotContainsString('site-skip', $blank);
        $this->assertStringNotContainsString('site-header', $blank);
    }

    /**
     * `layout.account` (COMPOSITIONS §3.7): the whole layout, the content moved into the grid of a
     * visitor's account beside its menu — empty here, the module of visitors fills `account.menu`.
     */
    #[Test]
    public function the_account_layout_puts_the_content_beside_its_menu(): void
    {
        $this->stubModules();

        $html = Blade::render('<x-layout.account handle="users.account"><p>Profile</p></x-layout.account>');

        $this->assertMatchesRegularExpression(
            '~<main id="content" class="site-main">\\s*<div class="site-account">\\s*<nav class="site-account__menu" aria-label="Account">\\s*</nav>\\s*<div class="site-account__body">\\s*<p>Profile</p>~',
            $html,
        );
        $this->assertStringContainsString('site-header', $html);
        $this->assertStringContainsString('site-footer', $html);
        $this->assertSame([], array_values(array_filter(app(Compositions::class)->findings(), static fn ($finding): bool => ! $finding->note)));
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

    /**
     * The words of the layout (rule 26): the ten languages of the panel, declared in `locales` and
     * each a directory of words with the keys of `en` — what `webx:theme:check` holds every layer to.
     */
    #[Test]
    public function the_words_have_the_same_keys_in_every_declared_language(): void
    {
        $languages = array_map('basename', glob(self::path().'/lang/*', GLOB_ONLYDIR) ?: []);

        $this->assertEqualsCanonicalizing(['de', 'en', 'es', 'fr', 'it', 'pl', 'pt', 'ru', 'tr', 'uk'], $languages);
        $this->assertEqualsCanonicalizing($languages, $this->manifest()->locales);
        $this->assertSame(['findings' => [], 'locales' => 10], LineCheck::check($this->manifest()));
    }

    /**
     * Inter as the theme's own files (§15.3, decision of 11.10.2026): latin, latin-ext and
     * cyrillic, upright and italic, with the OFL beside them — the licence travels with the font.
     * They cover every language the theme speaks, and the body's stack starts with them.
     */
    #[Test]
    public function inter_is_the_themes_own_and_draws_every_language_it_speaks(): void
    {
        $chain = ThemeChain::resolve('webx-ui/theme-default', $this->app->make(ThemeLocator::class));
        $check = FontCheck::check($chain, []);

        $this->assertSame([], $check['findings']);
        $this->assertSame(['Inter', 'Inter'], array_column($check['faces'], 'family'));
        $this->assertSame(['normal', 'italic'], array_column($check['faces'], 'style'));

        foreach ($check['faces'] as $face) {
            $this->assertSame(['latin-ext', 'latin', 'cyrillic'], array_values(array_intersect(['latin-ext', 'latin', 'cyrillic'], array_keys($face['files']))));
            $this->assertSame([], ThemeFonts::gaps([$face], $this->manifest()->locales));

            foreach ($face['files'] as $file) {
                $this->assertStringStartsWith('wOF2', (string) file_get_contents(self::path().'/assets/'.$file, length: 4), $file);
            }
        }

        $this->assertStringContainsString('SIL Open Font License, Version 1.1', (string) file_get_contents(self::path().'/assets/fonts/OFL.txt'));
        $this->assertStringStartsWith('Inter, ', json_decode((string) file_get_contents(self::path().'/tokens.json'), true)['defaults']['font-body']);
    }

    /**
     * No word of the shell or of a block is written into its template (§15.2): every text a
     * visitor reads there comes from a field, from the settings or from `theme::` — a text node or
     * an `aria-label`, `alt`, `title` or `placeholder` with letters of its own would stay English
     * on a Polish page. Every `__()` names a key `en` has.
     */
    #[Test]
    public function no_word_of_the_shell_or_of_a_block_or_of_the_showcase_is_written_into_its_template(): void
    {
        $words = Arr::dot(require self::path().'/lang/en/theme.php');
        $templates = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::path().'/views', RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            /** @var SplFileInfo $file */
            $templates['views/'.str_replace('\\', '/', substr($file->getPathname(), strlen(self::path().'/views/')))] = (string) file_get_contents($file->getPathname());
        }

        foreach (glob(self::path().'/blocks/*.json') ?: [] as $file) {
            $templates['blocks/'.basename($file)] = (string) (json_decode((string) file_get_contents($file), true)['template'] ?? '');
        }

        // The showcase's own block types too (TH4.4): a word in their templates is English on the Russian
        // and the Polish showcase, which is what the showcase is there to show.
        foreach (glob(self::path().'/demo/blocks/*.json') ?: [] as $file) {
            $templates['demo/blocks/'.basename($file)] = (string) (json_decode((string) file_get_contents($file), true)['template'] ?? '');
        }

        // The compositions too: what a node of raw HTML says is as much the template as a view.
        foreach (glob(self::path().'/compositions/*/*.json') ?: [] as $file) {
            $html = [];
            $tree = (array) json_decode((string) file_get_contents($file), true);
            array_walk_recursive($tree, static function (mixed $value, string|int $key) use (&$html): void {
                if ($key === 'html') {
                    $html[] = (string) $value;
                }
            });
            $templates['compositions/'.basename(dirname($file)).'/'.basename($file)] = implode("\n", $html);
        }

        $this->assertCount(54, $templates);

        foreach ($templates as $name => $template) {
            preg_match_all("/__\\(\\s*'([^']*)'/", $template, $calls);

            foreach ($calls[1] as $key) {
                $this->assertStringStartsWith('theme::theme.', $key, "{$name}: a word of the theme is theme::theme.<key>");
                $this->assertArrayHasKey(substr($key, strlen('theme::theme.')), $words, "{$name}: {$key}");
            }

            // What the page prints of the template itself: no comments, no PHP, no echoes.
            $markup = (string) preg_replace(['/\{\{--.*?--\}\}/s', '/@php.*?@endphp/s', '/\{\{.*?\}\}/s', '/\{!!.*?!!\}/s', '/@\w+(\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?/'], ' ', $template);

            preg_match_all('/>([^<>]*\p{L}{2,}[^<>]*)</u', $markup, $texts);
            $this->assertSame([], array_values(array_filter(array_map('trim', $texts[1]), static fn (string $text): bool => preg_match('/^&\w+;$/', $text) !== 1)), "{$name}: words in the template");

            // A bound attribute (:title="$heading") is PHP, not a word.
            preg_match_all('/(?<![:\w-])(aria-label|alt|title|placeholder)="([^"]*\p{L}[^"]*)"/u', $markup, $attributes);
            $this->assertSame([], $attributes[0], "{$name}: words in an attribute");
        }
    }

    /** The shell in the request's language: "Skip to content" is the first thing a keyboard meets. */
    #[Test]
    public function the_shell_speaks_the_language_of_the_page(): void
    {
        $this->stubModules();

        foreach (['en' => 'Skip to content', 'ru' => 'Перейти к содержимому', 'pl' => 'Przejdź do treści', 'tr' => 'İçeriğe geç'] as $locale => $skip) {
            $this->app->setLocale($locale);
            $html = Blade::render('<x-layout><p>Body</p></x-layout>');

            $this->assertStringContainsString("<a class=\"site-skip\" href=\"#content\">{$skip}</a>", $html, $locale);
            $this->assertStringNotContainsString('theme.shell', $html, $locale);
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

    protected function themePath(): string
    {
        return self::path();
    }

    private static function path(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }
}
