<?php

declare(strict_types=1);

namespace WebxUi\ThemeDefault\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\FirstScreen;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The first screen (THEMES §9.10): an eyebrow over the H1, the window's height with the words in
 * the middle or at the top, a badge under them — and a block saved before any of it printing what
 * it printed before: `Fixtures/before/hero.blade.php` is the template as it was.
 */
final class HeroTest extends TestCase
{
    private const string NAME = 'webx-ui/theme-default';

    private const array PICTURE = ['url' => '/media/coast.jpg', 'alt' => 'A coast at dawn', 'width' => 1920, 'height' => 1080];

    private const array BADGE = ['url' => '/media/ribbons.png', 'alt' => 'Two award ribbons', 'width' => 169, 'height' => 362];

    /** The fields this stage added: a block saved before has none of them. */
    private const array NEW = ['eyebrow', 'badge', 'height', 'position'];

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
            'two buttons on a tint, centred' => ['tone' => 'surface', 'align' => 'center', 'second_label' => 'Later', 'second_link' => null, 'second_url' => '/later'],
            'an old address' => ['tone' => 'plain', 'button_link' => null, 'button_url' => '/old'],
            'over a picture, edge to edge, with parallax' => ['tone' => 'image', 'image' => self::PICTURE, 'width' => 'bleed', 'parallax' => true, 'parallax_strength' => 'strong'],
            'sliding over the section above, on a picture' => ['tone' => 'image', 'image' => self::PICTURE, 'overlap' => true],
            'a tone nobody knows' => ['tone' => 'neon', 'no_reveal' => true],
            'a line alone' => ['title' => '', 'button_label' => ''],
            'nothing to say' => ['title' => '', 'subtitle' => ''],
        ];

        // A hero after the first: the first screen's priority is the one thing the first adds.
        FirstScreen::claim('hero');

        foreach ($saved as $case => $values) {
            $before = Blade::render((string) file_get_contents(__DIR__.'/Fixtures/before/hero.blade.php'), $this->data($values, saved: true), deleteCachedView: true);

            // Byte for byte: the new template adds no character to a block that has none of the new fields.
            $this->assertSame($before, $this->render($values, saved: true), $case);
            // The new fields at their defaults are the same block.
            $this->assertSame($before, $this->render($values), "{$case}, defaults");
        }
    }

    #[Test]
    public function the_new_fields_default_to_the_block_it_was(): void
    {
        foreach (['eyebrow' => '', 'badge' => null, 'height' => 'auto', 'position' => 'center'] as $id => $default) {
            $this->assertSame($default, self::type()['sample'][$id], "{$id} of the sample");
        }

        $this->assertSame('auto', self::field('height')['default']);
        $this->assertSame(['auto', 'screen'], array_column(self::field('height')['props']['options'], 'value'));
        $this->assertSame('center', self::field('position')['default']);
        $this->assertSame(['center', 'top'], array_column(self::field('position')['props']['options'], 'value'));
        $this->assertSame(['when' => 'height', 'is' => 'screen'], self::field('position')['visible']);

        $this->assertSame('wx-input', self::field('eyebrow')['type']);
        $this->assertTrue(self::field('eyebrow')['localized']);
        $this->assertSame(80, self::field('eyebrow')['props']['maxlength']);
        $this->assertSame('wx-media', self::field('badge')['type']);
        $this->assertSame('image', self::field('badge')['props']['accept']);
        $this->assertLessThanOrEqual(255, mb_strlen((string) self::type()['description']));
    }

    #[Test]
    public function the_eyebrow_stands_over_the_headline_and_an_empty_one_is_not_printed(): void
    {
        $html = $this->render(['eyebrow' => 'A coastal town']);

        $this->assertMatchesRegularExpression('~<div class="b-hero__inner"[^>]*>\s*<p class="b-hero__eyebrow">A coastal town</p>\s*<h1 class="b-hero__title">~', $html);
        $this->assertStringNotContainsString('b-hero__eyebrow', $this->render(['eyebrow' => '']));
        $this->assertStringContainsString('&lt;b&gt;', $this->render(['eyebrow' => '<b>']));
        // An eyebrow alone is something to say.
        $this->assertStringContainsString('<section', $this->render(['eyebrow' => 'Soon', 'title' => '', 'subtitle' => '']));

        $styles = (string) self::type()['styles'];
        $this->assertStringContainsString('.b-hero__eyebrow {', $styles);
        $this->assertStringContainsString('text-transform: var(--site-eyebrow-case, uppercase);', $styles);
    }

    #[Test]
    public function the_screen_puts_the_words_in_the_middle_or_at_the_top(): void
    {
        $this->assertStringContainsString('<section class="b-hero b-hero--accent b-hero--screen b-hero--at-middle" data-wx-block="hero">', $this->render(['height' => 'screen']));
        $this->assertStringContainsString('<section class="b-hero b-hero--accent b-hero--screen b-hero--at-top" data-wx-block="hero">', $this->render(['height' => 'screen', 'position' => 'top']));
        // A position nobody offered is the middle; it means nothing without the screen.
        $this->assertStringContainsString('b-hero--screen b-hero--at-middle"', $this->render(['height' => 'screen', 'position' => '"><b>']));
        $this->assertStringNotContainsString('b-hero--at-', $this->render(['position' => 'top']));
        $this->assertStringNotContainsString('b-hero--screen', $this->render(['height' => '100vh']));

        $styles = (string) self::type()['styles'];
        $this->assertStringContainsString('min-height: calc(100svh - var(--hero-above, 0px));', $styles);
        $this->assertStringContainsString('.b-hero.b-hero--screen.b-hero--at-top {', $styles);
        $this->assertStringContainsString('var(--hero-top-space, var(--site-space-8))', $styles);
        $this->assertStringContainsString('.b-hero--screen:first-of-type:where(.site-main > *):where(.site:has(.webx-header--overlay) *) {', $styles);
    }

    #[Test]
    public function the_badge_stands_under_the_words_and_the_buttons_unshaded(): void
    {
        $html = $this->render(['tone' => 'image', 'image' => self::PICTURE, 'height' => 'screen', 'position' => 'top', 'button_label' => '', 'badge' => self::BADGE]);

        $this->assertMatchesRegularExpression('~<p class="b-hero__subtitle">[^<]*</p>\s*<img class="b-hero__badge" src="/media/ribbons\.png" alt="Two award ribbons"\s+width="169" height="362"\s*fetchpriority="high">\s*</div>\s*</section>~', $html);
        // Inside the words, not under the shade; and the first screen is never lazy.
        $this->assertSame(1, substr_count($html, 'b-hero__shade'));
        $this->assertLessThan(strpos($html, 'b-hero__badge'), strpos($html, 'b-hero__shade'));
        $this->assertStringNotContainsString('loading="lazy"', $html);

        $this->assertMatchesRegularExpression('~</div>\s*<img class="b-hero__badge"~', $this->render(['badge' => self::BADGE]));
        $this->assertStringNotContainsString('b-hero__badge', $this->render(['badge' => ['url' => '', 'alt' => '']]));
        $this->assertStringContainsString('b-hero__badge', $this->render(['title' => '', 'subtitle' => '', 'badge' => self::BADGE]));
    }

    #[Test]
    public function the_first_hero_of_the_page_fetches_its_pictures_first(): void
    {
        $values = ['tone' => 'image', 'image' => self::PICTURE, 'height' => 'screen', 'badge' => self::BADGE];

        // An empty hero prints nothing and leaves the first screen to the next one.
        $this->assertSame('', trim($this->render(['title' => '', 'subtitle' => ''])));

        $first = $this->render($values);
        $this->assertMatchesRegularExpression('~<img class="b-hero__picture" [^>]*height="1080"\s*fetchpriority="high">~', $first);
        $this->assertMatchesRegularExpression('~<img class="b-hero__badge" [^>]*height="362"\s*fetchpriority="high">~', $first);
        $this->assertStringNotContainsString('loading=', $first);

        // A second hero of the same page waits its turn.
        $second = $this->render($values);
        $this->assertStringContainsString('b-hero__badge', $second);
        $this->assertStringNotContainsString('fetchpriority', $second);

        // The next request is a page of its own.
        $this->app->forgetScopedInstances();
        $this->assertSame(2, substr_count($this->render($values), 'fetchpriority="high"'));
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
     * block from before this stage — none of the new fields are there.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function data(array $values, bool $saved = false): array
    {
        $type = self::type();
        $data = [...array_fill_keys(array_column($type['schema'], 'id'), null), ...$type['sample'], ...$values];

        if ($saved) {
            foreach (self::NEW as $id) {
                unset($data[$id]);
            }
        }

        return $data;
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
        return json_decode((string) file_get_contents(self::path().'/blocks/hero.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function path(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }
}
