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
        $this->assertSame(['viewing'], \WebxUi\Widgets\FormDialogs::openers($html));
    }

    #[Test]
    public function edge_to_edge_is_for_every_tone(): void
    {
        $this->assertStringContainsString('<section class="b-cta b-cta--accent site-bleed"', $this->render(['width' => 'bleed']));
        $this->assertStringContainsString('<section class="b-cta b-cta--plain site-bleed"', $this->render(['width' => 'bleed', 'tone' => 'plain']));
        $this->assertStringContainsString('.b-cta.site-bleed .b-cta__inner {', (string) self::type()['styles']);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function render(array $values = [], bool $saved = false): string
    {
        return Blade::render((string) self::type()['template'], $this->data($values, $saved), deleteCachedView: true);
    }

    /**
     * Every field of the schema a variable as the renderer makes it, over the sample. Saved: a
     * block from before the picture — none of the new fields are there.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function data(array $values, bool $saved = false): array
    {
        $type = self::type();
        $data = [...array_fill_keys(array_column($type['schema'], 'id'), null), ...$type['sample'], ...$values];

        if ($saved) {
            foreach (['eyebrow', 'image', 'shade', 'parallax', 'parallax_strength', 'height', 'ratio', 'width'] as $id) {
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
