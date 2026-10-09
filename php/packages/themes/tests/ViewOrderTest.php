<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Each fixture view exists from its winning layer down, and its body names the layer, so a
 * view served from the wrong level says which one it came from.
 */
class ViewOrderTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function plainViews(): array
    {
        return [
            'site beats local' => ['site-wins', 'site'],
            'local beats default' => ['local-wins', 'local'],
            'default when nobody above has it' => ['default-wins', 'default'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function namespacedViews(): array
    {
        return [
            'site beats local' => ['webx-fake::site-wins', 'site'],
            'local beats default' => ['webx-fake::local-wins', 'local'],
            'default beats module' => ['webx-fake::default-wins', 'default'],
            'module when no theme overrides it' => ['webx-fake::module-only', 'module'],
        ];
    }

    #[Test]
    #[DataProvider('plainViews')]
    public function a_plain_view_is_looked_up_top_down(string $view, string $layer): void
    {
        $this->assertSame($layer, $this->layerOf($view));
    }

    #[Test]
    #[DataProvider('namespacedViews')]
    public function a_module_view_is_looked_up_top_down(string $view, string $layer): void
    {
        $this->assertSame($layer, $this->layerOf($view));
    }

    #[Test]
    public function the_layout_component_comes_from_the_chain(): void
    {
        // What the modules' `layout` config names: a component the theme supplies.
        $this->assertSame('<main class="default-layout">hi</main>', trim(Blade::render('<x-layout>hi</x-layout>')));
    }

    #[Test]
    public function the_namespace_is_site_then_layers_then_module(): void
    {
        $hints = array_map(
            fn (string $path) => str_replace('\\', '/', $path),
            $this->finder()->getHints()['webx-fake'],
        );

        $this->assertSame([
            self::fixture('site/resources/views/vendor/webx-fake'),
            self::fixture('site/theme/views/vendor/webx-fake'),
            self::fixture('theme-fixture/views/vendor/webx-fake'),
            self::fixture('module/views'),
        ], $hints);
    }
}
