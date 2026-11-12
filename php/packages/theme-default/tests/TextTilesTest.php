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
 * «Text and tiles on a photo» (THEMES §9.7): the words beside up to four glass tiles over a photo
 * under a shade — a tile an icon or a figure, an empty one left out, the parallax of the hero.
 */
final class TextTilesTest extends TestCase
{
    private const string NAME = 'webx-ui/theme-default';

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
    public function the_sample_prints_the_words_and_four_figures(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data-wx-block="text-tiles"', $html);
        $this->assertMatchesRegularExpression('~<section class="b-text-tiles b-text-tiles--dark b-text-tiles--4 b-text-tiles--words site-bleed"~', $html);
        $this->assertStringContainsString('<p class="b-text-tiles__eyebrow">The place</p>', $html);
        // The accent of the heading is the one of every block (§9.4).
        $this->assertStringContainsString('<h2 class="b-text-tiles__heading">Room to live, <span class="site-accent">close to everything</span></h2>', $html);
        $this->assertMatchesRegularExpression('~<a class="b-text-tiles__button"\s+href="/contacts"\s*>Book a viewing</a>~', $html);
        $this->assertSame(4, substr_count($html, '<li class="b-text-tiles__tile b-text-tiles__tile--value">'));
        $this->assertStringContainsString('<p class="b-text-tiles__value">120 <span class="b-text-tiles__unit">m²</span></p>', $html);
        // A figure without a unit is the figure alone.
        $this->assertStringContainsString('<p class="b-text-tiles__value">24/7</p>', $html);
        $this->assertStringContainsString('<div class="b-text-tiles__shade"></div>', $html);
        $this->assertStringContainsString('<img class="b-text-tiles__picture" src="/media/photo.jpg"', $html);
    }

    #[Test]
    public function a_block_saved_before_prints_what_it_printed_before(): void
    {
        $value = static fn (string $label): array => ['kind' => 'value', 'value' => '1', 'label' => $label];
        $saved = [
            'sample' => [],
            'light, in the column' => ['shade' => 'light', 'width' => 'column'],
            'one tile' => ['tiles' => [$value('One')]],
            'three tiles, no words' => ['eyebrow' => '', 'heading' => '', 'text' => '', 'button_label' => '', 'tiles' => [$value('A'), $value('B'), $value('C')]],
            'words alone, parallax' => ['tiles' => [], 'parallax' => true, 'parallax_strength' => 'soft'],
            'sliding over' => ['overlap' => true],
        ];

        foreach ($saved as $case => $values) {
            $before = Blade::render((string) file_get_contents(__DIR__.'/Fixtures/before/text-tiles.blade.php'), $this->data($values, saved: true), deleteCachedView: true);

            $this->assertSame(self::tight($before), self::tight($this->render($values, saved: true)), $case);
            // The new field at its default is the same block.
            $this->assertSame(self::tight($before), self::tight($this->render($values)), "{$case}, defaults");
        }

        $this->assertSame('free', self::field('tile_shape')['default'] ?? null);
        $this->assertSame('free', self::type()['sample']['tile_shape']);
        $this->assertSame('dark', self::field('shade')['default'] ?? null);
    }

    #[Test]
    public function square_tiles_stand_in_a_column_of_their_own(): void
    {
        $html = $this->render(['tile_shape' => 'square']);

        $this->assertMatchesRegularExpression('~<section class="b-text-tiles b-text-tiles--dark b-text-tiles--4 b-text-tiles--words b-text-tiles--square site-bleed"~', $html);
        $this->assertMatchesRegularExpression('~<div class="b-text-tiles__grid">\s*<ul class="b-text-tiles__tiles" role="list"[^>]*>.*</ul>\s*</div>~s', $html);
        $this->assertSame(4, substr_count($html, '<li class="b-text-tiles__tile'));

        foreach (['free', '"><script>', null] as $shape) {
            $other = $this->render(['tile_shape' => $shape]);
            $this->assertStringNotContainsString('b-text-tiles--square', $other);
            $this->assertStringNotContainsString('b-text-tiles__grid', $other);
        }
        $this->assertSame(['free', 'square'], array_column(self::field('tile_shape')['props']['options'], 'value'));

        $styles = (string) self::type()['styles'];
        // A square two across only where two fit; it grows with its words, the row is one height.
        $this->assertMatchesRegularExpression('~@container \(min-width: 30rem\) \{\s*\.b-text-tiles--square:not\(\.b-text-tiles--1\) \.b-text-tiles__tiles \{\s*grid-template-columns: repeat\(2, minmax\(0, 1fr\)\);~', $styles);
        $this->assertStringContainsString("align-self: stretch;\n        justify-self: stretch;\n        aspect-ratio: 1;", $styles);
        $this->assertStringContainsString('.b-text-tiles__grid {', $styles);
        // The spacing of a tile: knobs of the site's theme over the block's own steps.
        foreach (['var(--text-tiles-tile-padding, var(--site-space-4))', 'var(--text-tiles-tile-padding, var(--site-space-5))', 'var(--text-tiles-tile-gap, var(--site-space-2))', 'var(--text-tiles-tiles-gap, var(--site-space-3))'] as $knob) {
            $this->assertStringContainsString($knob, $styles);
        }
    }

    #[Test]
    public function the_gradient_is_the_dark_shade_fading_away_from_the_words(): void
    {
        $this->assertStringContainsString('b-text-tiles--gradient', $this->render(['shade' => 'gradient']));
        $this->assertSame(['dark', 'light', 'gradient'], array_column(self::field('shade')['props']['options'], 'value'));

        $styles = (string) self::type()['styles'];
        $this->assertStringContainsString(".b-text-tiles--gradient {\n    --text-tiles-fade: var(--text-tiles-shade);\n}", $styles);
        // Beside the words only; at the middle still the even shade's veil (WCAG AA for the words).
        $this->assertMatchesRegularExpression('~@container \(min-width: 56rem\) \{\s*\.b-text-tiles--gradient \.b-text-tiles__shade \{\s*background: linear-gradient\(\s*to right,\s*var\(--text-tiles-fade\) 0%,\s*color-mix\(in srgb, var\(--text-tiles-fade\) var\(--text-tiles-veil\), transparent\) 50%,\s*transparent 100%~', $styles);
        $this->assertStringContainsString('.b-text-tiles--gradient:dir(rtl) .b-text-tiles__shade {', $styles);
    }

    #[Test]
    public function a_tile_shows_an_icon_or_a_figure_never_both(): void
    {
        $html = $this->render(['tiles' => [
            ['kind' => 'icon', 'icon' => ['url' => '/media/leaf.png', 'alt' => 'Leaf', 'extension' => 'png', 'width' => 48, 'height' => 48], 'value' => '9', 'unit' => 'm', 'label' => 'Garden'],
            ['kind' => 'value', 'icon' => ['url' => '/media/leaf.png', 'extension' => 'png'], 'value' => '3', 'unit' => 'km', 'label' => 'To the sea'],
        ]]);

        $this->assertStringContainsString('<img class="b-text-tiles__icon" src="/media/leaf.png" alt="Leaf" loading="lazy"  width="48" height="48" >', $html);
        $this->assertSame(1, substr_count($html, 'b-text-tiles__icon'));
        $this->assertSame(1, substr_count($html, 'b-text-tiles__value'));
        $this->assertStringContainsString('<p class="b-text-tiles__value">3 <span class="b-text-tiles__unit">km</span></p>', $html);
        $this->assertStringContainsString('b-text-tiles--2', $html);
    }

    #[Test]
    public function an_svg_icon_takes_the_colour_of_the_words(): void
    {
        $html = $this->render(['tiles' => [
            ['kind' => 'icon', 'icon' => ['url' => "/media/a'b(1).svg", 'alt' => '', 'extension' => 'svg'], 'label' => 'Quiet'],
            ['kind' => 'icon', 'icon' => ['url' => '/media/sun.svg', 'alt' => 'Sun', 'mime' => 'image/svg+xml'], 'label' => 'Light'],
        ]]);

        // Its quotes and brackets cannot close the url() of the style attribute.
        $this->assertStringContainsString('<span class="b-text-tiles__icon b-text-tiles__icon--mask" style="--b-text-tiles-icon: url(\'/media/a%27b%281%29.svg\')"  aria-hidden="true" ></span>', $html);
        $this->assertStringContainsString('url(\'/media/sun.svg\')"  role="img" aria-label="Sun" ></span>', $html);
        $this->assertStringNotContainsString('<img class="b-text-tiles__icon"', $html);
    }

    #[Test]
    public function an_empty_tile_is_left_out_and_four_is_the_most(): void
    {
        $tile = static fn (string $label): array => ['kind' => 'value', 'value' => '1', 'label' => $label];

        $html = $this->render(['tiles' => [
            ['kind' => 'value', 'value' => '  ', 'unit' => 'm²', 'label' => '', 'note' => ''],
            // An icon tile keeps nothing of a figure typed before the kind was changed.
            ['kind' => 'icon', 'icon' => null, 'value' => '7', 'unit' => 'm²'],
            'not a tile',
            $tile('One'), $tile('Two'), $tile('Three'), $tile('Four'), $tile('Five'),
        ]]);

        $this->assertSame(4, substr_count($html, '<li class="b-text-tiles__tile'));
        $this->assertStringNotContainsString('m²', $html);
        $this->assertStringNotContainsString('Five', $html);

        foreach ([1, 3] as $count) {
            $this->assertStringContainsString("b-text-tiles--{$count} ", $this->render(['tiles' => array_map($tile(...), array_fill(0, $count, 'A'))]));
        }
    }

    #[Test]
    public function without_tiles_the_words_stand_alone_and_without_both_nothing(): void
    {
        $words = $this->render(['tiles' => []]);
        $this->assertStringContainsString('b-text-tiles--0 b-text-tiles--words', $words);
        $this->assertStringNotContainsString('b-text-tiles__tiles', $words);

        $tiles = $this->render(['eyebrow' => '', 'heading' => '', 'text' => '', 'button_label' => '']);
        $this->assertStringNotContainsString('b-text-tiles__words', $tiles);
        $this->assertStringNotContainsString('b-text-tiles--words', $tiles);
        $this->assertStringContainsString('b-text-tiles__tiles', $tiles);

        $this->assertSame('', trim($this->render(['eyebrow' => '', 'heading' => '', 'text' => '', 'button_label' => '', 'tiles' => null])));
        // A button without a link is not a button.
        $this->assertStringNotContainsString('b-text-tiles__button', $this->render(['button_link' => null]));
    }

    #[Test]
    public function the_shade_the_width_and_the_photo(): void
    {
        $this->assertStringContainsString('b-text-tiles--light', $this->render(['shade' => 'light']));
        // Anything else is the dark shade.
        $this->assertStringContainsString('b-text-tiles--dark', $this->render(['shade' => '"><script>']));
        $this->assertStringNotContainsString('site-bleed', $this->render(['width' => 'column']));
        // Without a photo the section is the shade's colour: nothing to draw, nothing to move.
        $plain = $this->render(['image' => null, 'parallax' => true]);
        $this->assertStringNotContainsString('<img', $plain);
        $this->assertStringNotContainsString('data-webx-parallax', $plain);
    }

    #[Test]
    public function the_parallax_is_the_one_of_the_hero(): void
    {
        $this->assertStringNotContainsString('data-webx-parallax', $this->render());
        $this->assertMatchesRegularExpression('~<img class="b-text-tiles__picture"\s+data-webx-parallax\s+src=~', $this->render(['parallax' => true]));
        $this->assertStringContainsString('data-webx-parallax="strong"', $this->render(['parallax' => true, 'parallax_strength' => 'strong']));
        $this->assertStringNotContainsString('data-webx-parallax="', $this->render(['parallax' => true, 'parallax_strength' => '"><script>']));
        $this->assertSame(1, substr_count($this->render(['parallax' => true]), 'data-webx-parallax'));
    }

    #[Test]
    public function the_glass_has_a_denser_fallback(): void
    {
        $styles = (string) self::type()['styles'];

        $this->assertStringContainsString('backdrop-filter: blur(var(--text-tiles-blur));', $styles);
        $this->assertStringContainsString('@supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px)))', $styles);
        $this->assertStringContainsString('var(--text-tiles-glass-solid)', $styles);

    }

    #[Test]
    public function the_tiles_are_four_at_most_in_the_panel_too(): void
    {
        $tiles = array_values(array_filter(self::type()['schema'], static fn (array $node): bool => ($node['id'] ?? null) === 'tiles'))[0] ?? null;

        $this->assertSame('wx-repeater', $tiles['type'] ?? null);
        $this->assertSame(4, $tiles['props']['max'] ?? null);
        $children = array_column($tiles['children'] ?? [], null, 'id');
        $this->assertSame(['when' => 'kind', 'is' => 'icon'], $children['icon']['visible'] ?? null);
        $this->assertSame(['when' => 'kind', 'is' => 'value'], $children['value']['visible'] ?? null);
        $this->assertSame(['when' => 'kind', 'is' => 'value'], $children['unit']['visible'] ?? null);
    }

    /**
     * The template on its sample over a photo, every field of the schema a variable as the
     * renderer makes it.
     *
     * @param  array<string, mixed>  $values
     */
    private function render(array $values = [], bool $saved = false): string
    {
        return Blade::render((string) self::type()['template'], $this->data($values, $saved), deleteCachedView: true);
    }

    /**
     * Saved: the field of the square tiles is absent, as a block saved before it reads.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function data(array $values, bool $saved = false): array
    {
        $type = self::type();
        $data = [
            ...array_fill_keys(array_column($type['schema'], 'id'), null),
            ...$type['sample'],
            'image' => ['url' => '/media/photo.jpg', 'alt' => '', 'width' => 1600, 'height' => 900],
            ...$values,
        ];

        if ($saved) {
            unset($data['tile_shape']);
        }

        return $data;
    }

    /** The indent a template line left out leaves before the next one, which the browser drops. */
    private static function tight(string $html): string
    {
        return trim((string) preg_replace(['~\s+~', '~ >~'], [' ', '>'], $html));
    }

    /** @return array<string, mixed> */
    private static function field(string $id): array
    {
        foreach (self::type()['schema'] as $node) {
            if (($node['id'] ?? null) === $id) {
                return $node;
            }
        }

        return [];
    }

    /** @return array<string, mixed> */
    private static function type(): array
    {
        return json_decode((string) file_get_contents(self::path().'/blocks/text-tiles.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function path(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }
}
