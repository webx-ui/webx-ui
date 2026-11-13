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
 * The parallax of the hero's picture (THEMES §9.5): a switch of the block, off by default, that
 * puts the widgets' `data-webx-parallax` on the picture behind the words — and, off, the markup
 * the block printed before it was there.
 */
final class ParallaxTest extends TestCase
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
    public function the_switch_is_off_by_default_and_only_for_a_picture(): void
    {
        $type = self::type();
        $field = array_values(array_filter($type['schema'], static fn (array $node): bool => ($node['id'] ?? null) === 'parallax'))[0] ?? null;

        $this->assertSame('wx-switch', $field['type'] ?? null);
        $this->assertFalse($field['default'] ?? null);
        $this->assertSame(['when' => 'tone', 'is' => 'image'], $field['visible'] ?? null);
        $this->assertFalse($type['sample']['parallax'] ?? null);
    }

    #[Test]
    public function off_the_hero_prints_what_it_printed_before(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('<img class="b-hero__picture" src="/media/picture.jpg"', $html);
        $this->assertStringNotContainsString('data-webx-parallax', $html);
        $this->assertSame($this->render(unmarked: true), $html);
    }

    #[Test]
    public function on_the_picture_behind_the_words_moves(): void
    {
        $html = $this->render(['parallax' => true]);

        $this->assertMatchesRegularExpression('~<img class="b-hero__picture"\s+data-webx-parallax\s+src="/media/picture\.jpg"~', $html);
        $this->assertSame(1, substr_count($html, 'data-webx-parallax'));
        // Neither the shade nor the words: they stay with the frame.
        $this->assertDoesNotMatchRegularExpression('~class="b-hero__(?:shade|inner)"[^>]*data-webx-parallax~', $html);
    }

    #[Test]
    public function the_strength_is_medium_unless_the_editor_says_otherwise(): void
    {
        $field = array_values(array_filter(self::type()['schema'], static fn (array $node): bool => ($node['id'] ?? null) === 'parallax_strength'))[0] ?? null;

        $this->assertSame('wx-segmented', $field['type'] ?? null);
        $this->assertSame('medium', $field['default'] ?? null);
        $this->assertSame(['soft', 'medium', 'strong'], array_column($field['props']['options'] ?? [], 'value'));
        $this->assertSame(['all' => [['when' => 'tone', 'is' => 'image'], ['when' => 'parallax', 'is' => true]]], $field['visible'] ?? null);

        // Medium — and a block saved before the field was there — is the attribute bare.
        $this->assertMatchesRegularExpression('~data-webx-parallax\s+src=~', $this->render(['parallax' => true]));
        $this->assertMatchesRegularExpression('~data-webx-parallax\s+src=~', $this->render(['parallax' => true, 'parallax_strength' => null]));
        $this->assertStringContainsString('data-webx-parallax="soft"', $this->render(['parallax' => true, 'parallax_strength' => 'soft']));
        $this->assertStringContainsString('data-webx-parallax="strong"', $this->render(['parallax' => true, 'parallax_strength' => 'strong']));
        // Something else written there is not printed: medium.
        $this->assertStringNotContainsString('data-webx-parallax="', $this->render(['parallax' => true, 'parallax_strength' => '"><script>']));
        // The strength alone moves nothing.
        $this->assertStringNotContainsString('data-webx-parallax', $this->render(['parallax_strength' => 'strong']));
    }

    #[Test]
    public function without_a_picture_there_is_nothing_to_move(): void
    {
        $this->assertStringNotContainsString('data-webx-parallax', $this->render(['parallax' => true, 'tone' => 'accent']));
        $this->assertStringNotContainsString('data-webx-parallax', $this->render(['parallax' => true, 'image' => null]));
    }

    /**
     * The hero's template on its sample over a picture, every field of the schema a variable as
     * the renderer makes it.
     *
     * @param  array<string, mixed>  $values
     */
    private function render(array $values = [], bool $unmarked = false): string
    {
        $type = self::type();
        $template = (string) $type['template'];
        // Each render a page of its own: the hero is its first, whichever render comes first.
        $this->app->forgetScopedInstances();

        if ($unmarked) {
            $template = (string) preg_replace('~@if \(\$parallax\) data-webx-parallax.*? @endif~', '', $template);
            $this->assertStringNotContainsString('parallax', $template);
        }

        $data = array_fill_keys(array_column($type['schema'], 'id'), null);
        $data = [
            ...$data,
            ...$type['sample'],
            'tone' => 'image',
            'image' => ['url' => '/media/picture.jpg', 'alt' => '', 'width' => 1600, 'height' => 900],
            ...$values,
        ];

        return Blade::render($template, $data, deleteCachedView: true);
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
