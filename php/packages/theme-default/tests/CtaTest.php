<?php

declare(strict_types=1);

namespace WebxUi\ThemeDefault\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\FormDialogs;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The call to action on a picture (THEMES §9.8): a picture under a shade behind the words, the
 * picture alone, a height by the words, the screen or a proportion — and a block saved before
 * any of it printing what it printed before: `Fixtures/before/cta.blade.php` is the template as
 * it was.
 */
final class CtaTest extends TestCase
{
    private const string NAME = 'webx-ui/theme-default';

    private const array PICTURE = ['url' => '/media/street.jpg', 'alt' => 'A street at dusk', 'width' => 1600, 'height' => 900];

    /** A link as `LinkType::resolve()` hands it to the template. */
    private const array FILE = ['target' => 'file', 'url' => '/media/plan.pdf', 'available' => true, 'new_tab' => false, 'rel' => null, 'download' => 'Plan.pdf'];

    private const array BROCHURE = ['url' => '/media/brochure.png', 'alt' => 'The brochure', 'width' => 640, 'height' => 820];

    /** The fields of the front picture, the gradients and the layouts (THEMES §9.11). */
    private const array NEW = ['foreground', 'layout', 'button_look'];

    private const array FORM = ['target' => 'url', 'url' => '#webx-form-viewing', 'available' => true, 'new_tab' => false, 'rel' => null, 'download' => null];

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
    public function a_block_saved_before_prints_what_it_printed_before(): void
    {
        $saved = [
            'sample' => [],
            'two old addresses on a tint' => ['tone' => 'surface', 'button_link' => null, 'button_url' => '/old', 'second_label' => 'Later', 'second_url' => '/later'],
            'on the page, a heading alone' => ['tone' => 'plain', 'text' => '', 'button_label' => '', 'button_link' => null],
            'a button alone' => ['heading' => '', 'text' => ''],
            'a tone nobody knows' => ['tone' => 'neon', 'no_reveal' => true],
            'sliding over the section above' => ['overlap' => true],
            'nothing to say' => ['heading' => '', 'button_label' => ''],
        ];

        foreach ($saved as $case => $values) {
            $before = Blade::render((string) file_get_contents(__DIR__.'/Fixtures/before/cta.blade.php'), $this->data($values, saved: true), deleteCachedView: true);

            $this->assertSame(self::tight($before), self::tight($this->render($values, saved: true)), $case);
            // The new fields at their defaults are the same block.
            $this->assertSame(self::tight($before), self::tight($this->render($values)), "{$case}, defaults");
        }
    }

    #[Test]
    public function the_new_fields_default_to_the_band_it_was(): void
    {
        $tone = self::field('tone');
        $this->assertSame('accent', $tone['default']);
        $this->assertSame(['accent', 'surface', 'plain', 'image'], array_column($tone['props']['options'], 'value'));

        foreach (['shade' => 'dark', 'parallax' => false, 'parallax_strength' => 'medium', 'height' => 'auto', 'ratio' => '16-9', 'width' => 'column'] as $id => $default) {
            $this->assertSame($default, self::field($id)['default'], $id);
            $this->assertSame($default, self::type()['sample'][$id], "{$id} of the sample");
        }

        foreach (['image', 'shade', 'parallax', 'height'] as $id) {
            $this->assertSame(['when' => 'tone', 'is' => 'image'], self::field($id)['visible'], $id);
        }

        $this->assertSame(['all' => [['when' => 'tone', 'is' => 'image'], ['when' => 'height', 'is' => 'ratio']]], self::field('ratio')['visible']);
        $this->assertSame(['auto', 'screen', 'ratio'], array_column(self::field('height')['props']['options'], 'value'));
        $this->assertArrayNotHasKey('visible', self::field('width'));
        $this->assertSame('wx-input', self::field('eyebrow')['type']);
        $this->assertTrue(self::field('eyebrow')['localized']);
        $this->assertLessThanOrEqual(255, mb_strlen((string) self::type()['description']));
    }

    #[Test]
    public function on_a_picture_the_words_stand_on_a_shade(): void
    {
        $html = $this->render(['tone' => 'image', 'image' => self::PICTURE, 'eyebrow' => 'Open house', 'heading' => 'Come ==and see==']);

        $this->assertStringContainsString('<section class="b-cta b-cta--image b-cta--dark" data-wx-block="cta">', $html);
        $this->assertMatchesRegularExpression('~<img class="b-cta__picture"\s+src="/media/street\.jpg" alt="A street at dusk" loading="lazy"\s+width="1600" height="900"\s*>\s*<div class="b-cta__shade"></div>~', $html);
        $this->assertStringContainsString('<p class="b-cta__eyebrow">Open house</p>', $html);
        $this->assertStringContainsString('<h2 class="b-cta__heading">Come <span class="site-accent">and see</span></h2>', $html);
        $this->assertStringNotContainsString('data-webx-parallax', $html);

        $this->assertStringContainsString('b-cta--image b-cta--light', $this->render(['tone' => 'image', 'image' => self::PICTURE, 'shade' => 'light']));
        // Something else written there is the dark shade.
        $this->assertStringContainsString('b-cta--image b-cta--dark', $this->render(['tone' => 'image', 'image' => self::PICTURE, 'shade' => '"><b>']));
        // The shade and the picture's fields are for a picture: another tone prints none of them.
        $accent = $this->render(['image' => self::PICTURE, 'shade' => 'light', 'parallax' => true, 'height' => 'screen']);
        $this->assertStringNotContainsString('b-cta__picture', $accent);
        $this->assertStringNotContainsString('b-cta--light', $accent);
        $this->assertStringNotContainsString('b-cta--screen', $accent);
    }

    #[Test]
    public function without_a_picture_the_band_is_the_colour_of_the_shade(): void
    {
        $html = $this->render(['tone' => 'image', 'image' => null]);

        $this->assertStringContainsString('<section class="b-cta b-cta--image b-cta--dark"', $html);
        $this->assertStringNotContainsString('b-cta__picture', $html);
        $this->assertStringContainsString('<div class="b-cta__shade"></div>', $html);
        // And with nothing to say and nothing to show, there is no block.
        $this->assertStringNotContainsString('<section', $this->render(['tone' => 'image', 'image' => ['url' => '', 'alt' => ''], 'heading' => '', 'button_label' => '']));
    }

    #[Test]
    public function the_picture_alone_is_the_picture_and_nothing_around_it(): void
    {
        $html = $this->render(['tone' => 'image', 'image' => self::PICTURE, 'width' => 'bleed', 'heading' => '', 'text' => '', 'button_label' => '', 'second_label' => '']);

        $this->assertStringContainsString('<section class="b-cta b-cta--image b-cta--dark b-cta--bare site-bleed" data-wx-block="cta">', $html);
        $this->assertStringContainsString('b-cta__picture', $html);
        $this->assertStringNotContainsString('b-cta__shade', $html);
        $this->assertStringNotContainsString('b-cta__words', $html);
        $this->assertStringNotContainsString('b-cta__actions', $html);

        $styles = (string) self::type()['styles'];
        $this->assertStringContainsString('.b-cta.b-cta--bare .b-cta__inner {', $styles);
        $this->assertStringContainsString('.b-cta--bare:not(.b-cta--screen, .b-cta--ratio) .b-cta__picture {', $styles);
    }

    #[Test]
    public function the_height_is_the_words_the_screen_or_a_proportion(): void
    {
        $base = ['tone' => 'image', 'image' => self::PICTURE];

        $this->assertStringContainsString('class="b-cta b-cta--image b-cta--dark"', $this->render($base + ['height' => 'auto']));
        $this->assertStringContainsString('class="b-cta b-cta--image b-cta--dark b-cta--screen"', $this->render($base + ['height' => 'screen']));
        $this->assertStringContainsString('class="b-cta b-cta--image b-cta--dark b-cta--ratio b-cta--ratio-21-9"', $this->render($base + ['height' => 'ratio', 'ratio' => '21-9']));
        // A proportion nobody offered is 16:9; a height nobody offered is by the words.
        $this->assertStringContainsString('b-cta--ratio b-cta--ratio-16-9"', $this->render($base + ['height' => 'ratio', 'ratio' => '7-1']));
        $this->assertStringNotContainsString('b-cta--ratio', $this->render($base + ['height' => '100vh']));

        $styles = (string) self::type()['styles'];
        foreach (array_column(self::field('ratio')['props']['options'], 'value') as $ratio) {
            $this->assertStringContainsString('.b-cta--ratio-'.$ratio.' .b-cta__inner {', $styles, $ratio);
            [$wide, $high] = explode('-', (string) $ratio);
            $this->assertStringContainsString("min-height: calc(100cqi * {$high} / {$wide});", $styles, $ratio);
        }
    }

    #[Test]
    public function the_picture_moves_with_the_parallax_of_the_hero(): void
    {
        $base = ['tone' => 'image', 'image' => self::PICTURE, 'parallax' => true];

        $this->assertMatchesRegularExpression('~<img class="b-cta__picture"\s+data-webx-parallax\s+src=~', $this->render($base));
        $this->assertStringContainsString('data-webx-parallax="strong"', $this->render($base + ['parallax_strength' => 'strong']));
        $this->assertStringNotContainsString('data-webx-parallax="', $this->render($base + ['parallax_strength' => '"><script>']));
        $this->assertSame(1, substr_count($this->render($base), 'data-webx-parallax'));
    }

    #[Test]
    public function a_button_downloads_a_file_and_another_opens_a_form(): void
    {
        $html = $this->render([
            'tone' => 'image',
            'image' => self::PICTURE,
            'button_label' => 'Download the plan',
            'button_link' => self::FILE,
            'second_label' => 'Book a viewing',
            'second_link' => self::FORM,
        ]);

        $this->assertMatchesRegularExpression('~<a class="b-cta__button"\s+href="/media/plan\.pdf" download="Plan\.pdf">Download the plan</a>~', $html);
        // What the widgets' form dialogs look for on the page (WIDGETS §6.4).
        $this->assertMatchesRegularExpression('~<a class="b-cta__button b-cta__button--quiet"\s+href="#webx-form-viewing">Book a viewing</a>~', $html);
        $this->assertSame(['viewing'], FormDialogs::openers($html));
    }

    #[Test]
    public function edge_to_edge_is_for_every_tone(): void
    {
        $this->assertStringContainsString('<section class="b-cta b-cta--accent site-bleed"', $this->render(['width' => 'bleed']));
        $this->assertStringContainsString('<section class="b-cta b-cta--plain site-bleed"', $this->render(['width' => 'bleed', 'tone' => 'plain']));
        $this->assertStringContainsString('.b-cta.site-bleed .b-cta__inner {', (string) self::type()['styles']);
    }

    #[Test]
    public function a_block_saved_with_a_picture_prints_byte_for_byte_what_it_printed(): void
    {
        $saved = [
            'sample' => [],
            'two buttons, a file and a form' => ['second_label' => 'Book', 'second_link' => self::FORM, 'button_link' => self::FILE],
            'a dark picture 21:9 edge to edge' => ['tone' => 'image', 'image' => self::PICTURE, 'height' => 'ratio', 'ratio' => '21-9', 'width' => 'bleed', 'eyebrow' => 'Open house'],
            'a light shade on the screen with parallax' => ['tone' => 'image', 'image' => self::PICTURE, 'shade' => 'light', 'height' => 'screen', 'parallax' => true, 'parallax_strength' => 'strong'],
            'the picture alone' => ['tone' => 'image', 'image' => self::PICTURE, 'heading' => '', 'text' => '', 'button_label' => ''],
            'a shade with no picture, sliding over' => ['tone' => 'image', 'overlap' => true],
            'a tint, without animation' => ['tone' => 'surface', 'no_reveal' => true],
            'nothing to say' => ['heading' => '', 'button_label' => ''],
        ];

        foreach ($saved as $case => $values) {
            $before = Blade::render((string) file_get_contents(__DIR__.'/Fixtures/before/cta-picture.blade.php'), $this->data($values, saved: true, only: self::NEW), deleteCachedView: true);

            // Byte for byte: no character added to a block that has none of the new fields.
            $this->assertSame($before, $this->render($values, saved: true, only: self::NEW), $case);
            $this->assertSame($before, $this->render($values), "{$case}, defaults");
        }
    }

    #[Test]
    public function the_front_picture_the_gradients_and_the_layouts_default_to_the_band_it_was(): void
    {
        foreach (['foreground' => null, 'layout' => 'bottom', 'button_look' => 'filled'] as $id => $default) {
            $this->assertSame($default, self::type()['sample'][$id], "{$id} of the sample");
        }

        $this->assertSame('bottom', self::field('layout')['default']);
        $this->assertSame(['bottom', 'split', 'top-center'], array_column(self::field('layout')['props']['options'], 'value'));
        $this->assertSame(['dark', 'light', 'gradient-start', 'gradient-top'], array_column(self::field('shade')['props']['options'], 'value'));
        $this->assertSame('filled', self::field('button_look')['default']);
        $this->assertSame(['filled', 'outline', 'primary-dark'], array_column(self::field('button_look')['props']['options'], 'value'));
        $this->assertArrayNotHasKey('visible', self::field('button_look'));
        $this->assertSame('wx-media', self::field('foreground')['type']);

        foreach (['foreground', 'layout'] as $id) {
            $this->assertSame(['when' => 'tone', 'is' => 'image'], self::field($id)['visible'], $id);
        }
    }

    #[Test]
    public function the_front_picture_stands_over_the_shade_beside_the_words(): void
    {
        $html = $this->render(['tone' => 'image', 'image' => self::PICTURE, 'shade' => 'gradient-start', 'layout' => 'split', 'foreground' => self::BROCHURE, 'button_look' => 'outline']);

        $this->assertStringContainsString('<section class="b-cta b-cta--image b-cta--gradient-start b-cta--split b-cta--with-foreground" data-wx-block="cta">', $html);
        // After the shade and the words, before the buttons: nothing lies over it.
        $this->assertMatchesRegularExpression('~<div class="b-cta__shade"></div>.*</div>\s*<img class="b-cta__foreground" src="/media/brochure\.png" alt="The brochure" loading="lazy"\s+width="640" height="820"\s*>\s*<div class="b-cta__actions">~s', $html);
        $this->assertStringContainsString('<a class="b-cta__button b-cta__button--outline"', $html);

        // The front picture alone is something to show, and not the bare picture.
        $alone = $this->render(['tone' => 'image', 'image' => self::PICTURE, 'foreground' => self::BROCHURE, 'heading' => '', 'text' => '', 'button_label' => '']);
        $this->assertStringContainsString('b-cta__foreground', $alone);
        $this->assertStringNotContainsString('b-cta--bare', $alone);
        $this->assertStringContainsString('<section', $this->render(['tone' => 'image', 'foreground' => self::BROCHURE, 'heading' => '', 'button_label' => '']));

        // A picture's fields: another tone prints none of them.
        $accent = $this->render(['foreground' => self::BROCHURE, 'layout' => 'split', 'shade' => 'gradient-top']);
        $this->assertStringNotContainsString('b-cta__foreground', $accent);
        $this->assertStringNotContainsString('b-cta--split', $accent);
        $this->assertStringNotContainsString('gradient', $accent);
    }

    #[Test]
    public function at_the_top_in_the_middle_under_a_gradient_from_the_top(): void
    {
        $html = $this->render(['tone' => 'image', 'image' => self::PICTURE, 'shade' => 'gradient-top', 'layout' => 'top-center', 'button_look' => 'primary-dark']);

        $this->assertStringContainsString('<section class="b-cta b-cta--image b-cta--gradient-top b-cta--top-center" data-wx-block="cta">', $html);
        $this->assertStringContainsString('<a class="b-cta__button b-cta__button--primary-dark"', $html);
        // What nobody offered is the band it was.
        $odd = $this->render(['tone' => 'image', 'image' => self::PICTURE, 'shade' => 'gradient', 'layout' => '"><b>', 'button_look' => 'neon']);
        $this->assertStringContainsString('<section class="b-cta b-cta--image b-cta--dark" data-wx-block="cta">', $odd);
        $this->assertStringContainsString('<a class="b-cta__button" ', $odd);
        // The look of the button is for every tone.
        $this->assertStringContainsString('b-cta__button b-cta__button--outline', $this->render(['button_look' => 'outline']));

        $styles = (string) self::type()['styles'];
        foreach ([
            '.b-cta--gradient-start .b-cta__shade {',
            '.b-cta--gradient-top .b-cta__shade {',
            'linear-gradient(to bottom, var(--cta-shade) var(--cta-gradient-hold, 38%)',
            '.b-cta--image.b-cta--split .b-cta__inner {',
            '.b-cta--image.b-cta--top-center .b-cta__inner {',
            'padding-block-start: var(--cta-top-space, var(--site-space-8));',
            'max-width: var(--cta-column-width, 46.75rem);',
            'border-radius: var(--cta-foreground-radius, var(--site-radius-lg));',
            'padding-block: var(--cta-space, var(--site-space-8));',
            '.b-cta .b-cta__button.b-cta__button--outline {',
            '.b-cta .b-cta__button.b-cta__button--primary-dark {',
        ] as $rule) {
            $this->assertStringContainsString($rule, $styles);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>|null  $only
     */
    private function render(array $values = [], bool $saved = false, ?array $only = null): string
    {
        return Blade::render((string) self::type()['template'], $this->data($values, $saved, $only), deleteCachedView: true);
    }

    /**
     * Every field of the schema a variable as the renderer makes it, over the sample. Saved: a
     * block from before the picture — none of the new fields are there; or, with `$only`, a block
     * from before those fields alone.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>|null  $only
     * @return array<string, mixed>
     */
    private function data(array $values, bool $saved = false, ?array $only = null): array
    {
        $type = self::type();
        $data = [...array_fill_keys(array_column($type['schema'], 'id'), null), ...$type['sample'], ...$values];

        if ($saved) {
            foreach ($only ?? ['eyebrow', 'image', 'shade', 'parallax', 'parallax_strength', 'height', 'ratio', 'width', ...self::NEW] as $id) {
                unset($data[$id]);
            }
        }

        return $data;
    }

    /** The indent a template line left out leaves before the next one, which the browser drops. */
    private static function tight(string $html): string
    {
        return trim((string) preg_replace('~\s+~', ' ', $html));
    }

    /** @return array<string, mixed> */
    private static function field(string $id): array
    {
        foreach (self::type()['schema'] as $node) {
            if (($node['id'] ?? null) === $id) {
                return $node;
            }
        }

        self::fail("No field [{$id}].");
    }

    /** @return array<string, mixed> */
    private static function type(): array
    {
        return json_decode((string) file_get_contents(self::path().'/blocks/cta.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function path(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }
}
