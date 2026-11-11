<?php

declare(strict_types=1);

namespace WebxUi\ThemeDefault\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeManifest;
use WebxUi\Themes\ThemeReveal;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The entrance of the six blocks on scroll (THEMES §9.3): off unless the theme names it, and then
 * `data-webx-reveal` on the words of each block and `stagger` on its lists; a block can say no.
 */
final class RevealTest extends TestCase
{
    private const string NAME = 'webx-ui/theme-default';

    /** Each block, its sample, and what comes in: the element's class → the mode printed on it. */
    private const array MARKED = [
        'hero' => ['b-hero__inner' => 'up'],
        'text' => ['b-text__inner' => 'up'],
        'media-text' => ['b-media-text__grid' => 'stagger'],
        'cards' => ['b-cards__head' => 'up', 'b-cards__grid' => 'stagger'],
        'cta' => ['b-cta__inner' => 'up'],
        'contact' => ['b-contact__head' => 'up', 'b-contact__grid' => 'stagger'],
        'text-tiles' => ['b-text-tiles__words' => 'up', 'b-text-tiles__tiles' => 'stagger'],
    ];

    /**
     * The contacts and the form are module-settings' and module-inbox's, not here: `contact` is
     * drawn without them, its head and an empty grid.
     *
     * @var array<string, array<string, mixed>>
     */
    private const array VALUES = ['contact' => ['show_contacts' => false, 'form' => '']];

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ThemeServiceProvider::class, WidgetsServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('webx-themes.theme', self::NAME);
        $app->resolving(ThemeLocator::class, function (ThemeLocator $locator): void {
            $locator->register(self::NAME, self::path());
        });
    }

    #[Test]
    public function the_theme_brings_nothing_in_unless_a_layer_names_it(): void
    {
        $this->assertNull($this->app->make(ThemeChain::class)->reveal());
        $this->assertNull(ThemeManifest::fromPackage(self::path())->reveal);
    }

    #[Test]
    public function off_every_block_prints_what_it_printed_before(): void
    {
        foreach (array_keys(self::MARKED) as $slug) {
            $html = $this->render($slug);

            $this->assertStringNotContainsString('data-webx-reveal', $html, $slug);
            // The template without its marks is the template as it was: the same markup, give or
            // take the space the mark stands after inside a tag. The words a section slid over
            // fades (`data-webx-overlap-fade`) carry their mark always: an attribute no stylesheet reads.
            $this->assertSame(self::tight($this->render($slug, unmarked: true)), self::tight(str_replace(' data-webx-overlap-fade', '', $html)), $slug);
        }
    }

    #[Test]
    public function on_the_words_come_in_and_the_lists_one_after_another(): void
    {
        $this->reveal('fade');

        foreach (self::MARKED as $slug => $marked) {
            $html = $this->render($slug);

            foreach ($marked as $class => $mode) {

                $expected = $mode === 'stagger' ? 'data-webx-reveal="stagger" data-webx-reveal-effect="fade"' : 'data-webx-reveal="fade"';
                $this->assertMatchesRegularExpression('~class="'.preg_quote($class, '~').'[^"]*"[^>]*?\s'.preg_quote($expected, '~').'[\s>]~', $html, "{$slug}: {$class}");
            }

            // Never the section itself: one thing fading inside another fades twice.
            $this->assertDoesNotMatchRegularExpression('~<section[^>]*data-webx-reveal~', $html, $slug);
            $this->assertSame(count($marked), substr_count($html, 'data-webx-reveal="'), $slug);
        }
    }

    #[Test]
    public function a_block_that_says_no_comes_in_at_once(): void
    {
        $this->reveal('up');

        foreach (array_keys(self::MARKED) as $slug) {
            $this->assertStringNotContainsString('data-webx-reveal', $this->render($slug, ['no_reveal' => true]), $slug);
        }
    }

    #[Test]
    public function every_block_has_the_switch_off_by_default(): void
    {
        foreach (array_keys(self::MARKED) as $slug) {
            $type = self::type($slug);
            $field = array_values(array_filter($type['schema'], static fn (array $node): bool => ($node['id'] ?? null) === 'no_reveal'))[0] ?? null;

            $this->assertSame('wx-switch', $field['type'] ?? null, $slug);
            $this->assertFalse($field['default'] ?? null, $slug);
            $this->assertFalse($type['sample']['no_reveal'] ?? null, $slug);
        }
    }

    /** The chain as a local theme on top of this one would make it, saying `"reveal": "<effect>"`. */
    private function reveal(string $effect): void
    {
        $chain = new ThemeChain([new ThemeManifest('theme', '/theme', true, reveal: $effect), ThemeManifest::fromPackage(self::path())]);
        $this->app->instance(ThemeReveal::class, new ThemeReveal($chain));
    }

    /**
     * The block's template on its sample, every field of the schema a variable as the renderer
     * makes it — a field the sample leaves out is null.
     *
     * @param  array<string, mixed>  $values
     */
    private function render(string $slug, array $values = [], bool $unmarked = false): string
    {
        $type = self::type($slug);
        $template = (string) $type['template'];

        if ($unmarked) {
            $template = (string) preg_replace("~ @webxReveal\\('[a-z]*', \\\$no_reveal\\)~", '', $template);
            $this->assertStringNotContainsString('@webxReveal', $template, $slug);
            // And without the section sliding over the one above (OverlapTest), off by default.
            $template = str_replace([' data-webx-overlap-fade', '@webxOverlap($overlap ?? false)'], '', $template);
            $template = (string) preg_replace('~\n *@webxOverlapEdge\([^\n]*\)|\n *@if \(\$framed\)\n[^\n]*\n *@endif~', '', $template);
        }

        $data = array_fill_keys(array_column($type['schema'], 'id'), null);
        $data = [...$data, ...$type['sample'], ...(self::VALUES[$slug] ?? []), ...$values];

        // A picture, so the parts drawn only around one are drawn.
        if (array_key_exists('image', $data)) {
            $data['image'] = ['url' => '/media/picture.jpg', 'alt' => '', 'width' => 1200, 'height' => 800];
        }

        return Blade::render($template, $data, deleteCachedView: true);
    }

    /** The spaces before a tag closes, which the browser drops and a mark that printed nothing leaves. */
    private static function tight(string $html): string
    {
        // And the run of spaces a directive that printed nothing leaves between two tags: the browser drops it.
        return (string) preg_replace(['~"\s+>~', '~>\s+<~'], ['">', ">\n<"], $html);
    }

    /** @return array<string, mixed> */
    private static function type(string $slug): array
    {
        return json_decode((string) file_get_contents(self::path()."/blocks/{$slug}.json"), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function path(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }
}
