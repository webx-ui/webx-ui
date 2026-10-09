<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Foundation\Application;
use Illuminate\View\FileViewFinder;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Themes\Tests\Fixtures\FakeModuleServiceProvider;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * The fake module comes after the themes on purpose: a theme that only saw the namespaces
     * registered before it booted would pass with the opposite order and fail on a real site.
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ThemeServiceProvider::class,
            FakeModuleServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [self::fixture('site/resources/views')]);
        $app['config']->set('webx-themes.theme', $this->theme());

        // The fixtures are not installed by Composer, so the locator is told where they are.
        // Resolving rather than binding: the provider's own singleton must stay the one in use.
        $app->resolving(ThemeLocator::class, function (ThemeLocator $locator): void {
            foreach (['theme-fixture' => 'webx-ui/theme-fixture', 'cycle-a' => 'fixture/cycle-a', 'cycle-b' => 'fixture/cycle-b',
                'diamond-top' => 'fixture/diamond-top', 'diamond-left' => 'fixture/diamond-left',
                'diamond-right' => 'fixture/diamond-right', 'not-a-theme' => 'fixture/not-a-theme'] as $dir => $name) {
                $locator->register($name, self::fixture($dir));
            }
        });
    }

    /** What `webx-themes.theme` holds for the test; absolute, because the fixture site is not the base path. */
    protected function theme(): string
    {
        return self::fixture('site/theme');
    }

    /** The finder `view()` asks, so the order under test is the order a page gets. */
    protected function finder(): FileViewFinder
    {
        $finder = $this->app->make('view')->getFinder();
        $this->assertInstanceOf(FileViewFinder::class, $finder);

        return $finder;
    }

    /** Which layer a view came from: each fixture's whole body is its layer's name. */
    protected function layerOf(string $view): string
    {
        return trim((string) file_get_contents($this->finder()->find($view)));
    }

    protected static function fixture(string $path): string
    {
        return str_replace('\\', '/', __DIR__).'/Fixtures/'.$path;
    }
}
