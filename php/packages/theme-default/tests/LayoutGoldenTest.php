<?php

declare(strict_types=1);

namespace WebxUi\ThemeDefault\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Compositions\Compositions;
use WebxUi\Compositions\Testing\IsolatedCompositions;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The page shell, byte for byte (COMPOSITIONS §10, decision 6): what the layout printed before it
 * became a composition is kept in `Fixtures/golden/`, and the layout has to print exactly that —
 * without the modules that add to it and with them. Whatever the theme and the modules print is
 * stubbed, so the files hold the shell and nothing a token or a module could move.
 *
 * The layout is a composition now: these are what its tree prints without a patch.
 *
 * `WEBX_GOLDEN=update` rewrites the files; a change of the shell is then read in the diff.
 */
final class LayoutGoldenTest extends TestCase
{
    // A compiled set of its own: the next test registers other modules.
    use IsolatedCompositions;

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
        $app['config']->set('app.name', 'Harbor Logistics');
        $app['config']->set('webx-themes.theme', self::NAME);
        $app['config']->set('view.paths', [self::fixtures().'/site']);
        $this->isolateCompositions($app);

        $app->resolving(ThemeLocator::class, function (ThemeLocator $locator): void {
            $locator->register(self::NAME, dirname(__DIR__));
        });
    }

    /** @return array<string, array{string, bool}> */
    public static function pages(): array
    {
        return [
            'bare' => ['<x-layout><p>Body of the page</p></x-layout>', false],
            'modules' => ['<x-layout><p>Body of the page</p></x-layout>', true],
            'head' => ['<x-layout><x-slot:head><title>From the page</title></x-slot:head><p>Body of the page</p></x-layout>', true],
        ];
    }

    #[Test]
    #[DataProvider('pages')]
    public function the_layout_prints_the_shell_it_always_printed(string $page, bool $modules): void
    {
        $this->stub($modules);

        $html = Blade::render($page);
        $golden = self::fixtures().'/golden/'.$this->dataName().'.html';

        if (getenv('WEBX_GOLDEN') === 'update') {
            @mkdir(dirname($golden), 0777, true);
            file_put_contents($golden, $html);
        }

        $this->assertFileExists($golden);
        $this->assertSame((string) file_get_contents($golden), $html);
    }

    /** The theme, its colour, module-seo, module-blocks and module-snippets as one comment each. */
    private function stub(bool $modules): void
    {
        Blade::directive('webxTheme', static fn (): string => '<?php echo "<!-- theme -->"; ?>');
        Blade::directive('webxThemeColor', static fn (): string => '<?php echo "<!-- theme-color -->"; ?>');
        Blade::directive('webxSeo', static fn (): string => '<?php echo "<!-- seo -->"; ?>');
        Blade::directive('webxBlocks', static fn (): string => '<?php echo "<!-- blocks -->"; ?>');
        Blade::anonymousComponentPath(self::fixtures().'/webx-blocks', 'webx-blocks');

        if ($modules) {
            View::addNamespace('webx-seo', self::fixtures().'/webx-seo');
            Blade::anonymousComponentPath(self::fixtures().'/webx-seo', 'webx-seo');
            View::addNamespace('webx-snippets', self::fixtures().'/webx-snippets');
            Blade::anonymousComponentPath(self::fixtures().'/webx-snippets/components');

            // Their real pieces of the layout, over stand-ins for what the pieces print.
            Compositions::load(dirname(__DIR__, 2).'/module-seo/resources/compositions');
            Compositions::load(dirname(__DIR__, 2).'/module-snippets/resources/compositions');
        }

        $this->artisan('view:clear');
    }

    private static function fixtures(): string
    {
        return str_replace('\\', '/', __DIR__).'/Fixtures';
    }
}
