<?php

declare(strict_types=1);

namespace WebxUi\ThemeDefault\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The accented words of the six blocks' headings (THEMES §9.4) and the logo of `media-text`: a
 * heading without marks and a block without a logo print exactly what they printed before.
 */
final class AccentTest extends TestCase
{
    private const string NAME = 'webx-ui/theme-default';

    /** Each block, the field of its heading and the tag it prints in. */
    private const array HEADINGS = [
        'hero' => ['title', 'h1'],
        'text' => ['heading', 'h2'],
        'media-text' => ['heading', 'h2'],
        'cards' => ['heading', 'h2'],
        'cta' => ['heading', 'h2'],
        'contact' => ['heading', 'h2'],
        'text-tiles' => ['heading', 'h2'],
    ];

    /** @var array<string, array<string, mixed>> */
    private const array VALUES = ['contact' => ['show_contacts' => false, 'form' => '']];

    private const array LOGO = ['url' => '/media/mark.svg', 'alt' => 'A mark', 'width' => 160, 'height' => 40];

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
    public function a_heading_without_marks_prints_what_it_printed_before(): void
    {
        foreach (self::HEADINGS as $slug => [$field]) {
            $values = [$field => 'Tom & Jerry'];

            $this->assertStringContainsString('Tom &amp; Jerry', $this->render($slug, $values), $slug);
            $this->assertSame(self::tight($this->render($slug, $values, before: true)), self::tight($this->render($slug, $values)), $slug);
        }
    }

    #[Test]
    public function marked_words_are_the_accent_inside_the_one_heading(): void
    {
        foreach (self::HEADINGS as $slug => [$field, $tag]) {
            $html = $this->render($slug, [$field => 'Homes ==by the river==']);

            $this->assertMatchesRegularExpression('~<'.$tag.' class="[^"]+">Homes <span class="site-accent">by the river</span></'.$tag.'>~', $html, $slug);
            $this->assertSame(1, substr_count($html, '<'.$tag.' '), $slug);
            $this->assertStringNotContainsString('==', $html, $slug);
        }
    }

    #[Test]
    public function every_heading_says_how_to_mark_its_words(): void
    {
        foreach (self::HEADINGS as $slug => [$field]) {
            $node = self::field($slug, $field);

            $this->assertStringContainsString('==', (string) ($node['help'] ?? ''), $slug);
            $this->assertStringContainsString("@webxAccent(\${$field})", (string) self::type($slug)['template'], $slug);
        }
    }

    #[Test]
    public function the_accent_takes_the_theme_s_colour_and_leans_where_the_colour_is_the_background(): void
    {
        $css = (string) file_get_contents(self::path().'/src/css/base.css');
        $this->assertMatchesRegularExpression('~:where\(\.site-accent\)\s*\{\s*color: var\(--site-color-accent\);~', $css);
        $this->assertStringContainsString(':where(.site) :where(.site-accent)', (string) file_get_contents(self::path().'/dist/theme.css'));

        foreach (['hero' => ['accent', 'image'], 'cta' => ['accent']] as $slug => $tones) {
            foreach ($tones as $tone) {
                $this->assertStringContainsString(".b-{$slug}--{$tone} .site-accent", (string) self::type($slug)['styles'], "{$slug}: {$tone}");
            }
        }
    }

    #[Test]
    public function media_text_without_a_logo_prints_what_it_printed_before(): void
    {
        $this->assertSame(self::tight($this->render('media-text', ['heading' => 'Words'], before: true)), self::tight($this->render('media-text', ['heading' => 'Words'])));
        $this->assertStringNotContainsString('b-media-text__logo', $this->render('media-text', ['logo' => ['url' => '', 'alt' => '']]));

        $node = self::field('media-text', 'logo');
        $this->assertSame('wx-media', $node['type'] ?? null);
        $this->assertSame('image', $node['props']['accept'] ?? null);
        $this->assertArrayNotHasKey('default', $node);
        $this->assertNull(self::type('media-text')['sample']['logo'] ?? null);
    }

    #[Test]
    public function media_text_prints_its_logo_above_the_heading_with_the_library_s_alt(): void
    {
        $html = $this->render('media-text', ['logo' => self::LOGO, 'heading' => 'Words']);

        $this->assertMatchesRegularExpression('~<div class="b-media-text__words site-prose">\s*<img class="b-media-text__logo" src="/media/mark.svg" alt="A mark" loading="lazy"\s+width="160" height="40"\s*>\s*<h2~', $html);

        // A logo alone is still a block.
        $alone = $this->render('media-text', ['logo' => self::LOGO, 'image' => null, 'heading' => null, 'body' => null, 'button_label' => null]);
        $this->assertStringContainsString('b-media-text__logo', $alone);
    }

    #[Test]
    public function the_logo_keeps_its_proportions_within_a_small_box(): void
    {
        $styles = (string) self::type('media-text')['styles'];
        preg_match('~\.b-media-text__logo \{([^}]*)\}~', $styles, $rule);
        $rule = $rule[1] ?? '';

        foreach (['width: auto', 'height: auto', 'max-height: var(--site-space-8)', 'max-width: min(100%, 12rem)', 'object-fit: contain'] as $declaration) {
            $this->assertStringContainsString($declaration, $rule);
        }
    }

    /**
     * The block's template on its sample, every field a variable as the renderer makes it. Before:
     * the template as it was — the heading through `{{ }}` and no logo.
     *
     * @param  array<string, mixed>  $values
     */
    private function render(string $slug, array $values = [], bool $before = false): string
    {
        $type = self::type($slug);
        $template = (string) $type['template'];

        if ($before) {
            $template = (string) preg_replace('~@webxAccent\((\$\w+)\)~', '{{ $1 }}', $template);
            $template = (string) preg_replace('~\n\s*@if \(\$mark\)\n.*?\n\s*@endif~s', '', $template);
            $template = str_replace(['$picture || $mark || ', "    \$mark = is_array(\$logo ?? null) && ! empty(\$logo['url']) ? \$logo : null;\n"], ['$picture || ', ''], $template);
            $this->assertStringNotContainsString('$mark', $template, $slug);
        }

        $data = array_fill_keys(array_column($type['schema'], 'id'), null);
        $data = [...$data, ...$type['sample'], ...(self::VALUES[$slug] ?? []), ...$values];

        if (array_key_exists('image', $data) && ! array_key_exists('image', $values)) {
            $data['image'] = ['url' => '/media/picture.jpg', 'alt' => '', 'width' => 1200, 'height' => 800];
        }

        if ($before) {
            unset($data['logo']);
        }

        return Blade::render($template, $data, deleteCachedView: true);
    }

    /** The indent a template line left out leaves before the next one, which the browser drops. */
    private static function tight(string $html): string
    {
        return (string) preg_replace('~\s+~', ' ', $html);
    }

    /** @return array<string, mixed> */
    private static function field(string $slug, string $id): array
    {
        foreach (self::type($slug)['schema'] as $node) {
            if (($node['id'] ?? null) === $id) {
                return $node;
            }
        }

        return [];
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
