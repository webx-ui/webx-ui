<?php

declare(strict_types=1);

namespace WebxUi\Compositions\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Compositions\Compiler;
use WebxUi\Compositions\Compositions;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Compositions\Testing\IsolatedCompositions;
use WebxUi\Compositions\Tree;

/**
 * A packaged theme with the base of `layout` and `layout.minimal`, two modules that add to it, and
 * a local theme in a directory of the test's own — the layers of COMPOSITIONS §5, as fixtures.
 */
abstract class TestCase extends Orchestra
{
    use IsolatedCompositions;

    /** The local theme's directory, written to by the test. */
    protected string $local = '';

    protected function setUp(): void
    {
        parent::setUp();

        // A test that reaches the network waits on it; whatever it calls is faked.
        Http::preventStrayRequests();

        Blade::directive('fixtureHead', static fn (): string => '<?php echo "<title>Fixture</title>"; ?>');
        Blade::anonymousComponentPath(self::fixtures().'/views/components');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->local);

        parent::tearDown();
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [CompositionsServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $this->isolateCompositions($app);
        $this->local = sys_get_temp_dir().'/webx-compositions-theme-'.str_replace('.', '', uniqid('', true));
        mkdir($this->local.'/compositions', 0777, true);

        $app['config']->set('view.paths', [self::fixtures().'/views', self::fixtures().'/theme/views']);

        $local = $this->local;
        $app->booted(static function () use ($local): void {
            Compositions::themes(static fn (): array => [
                ['name' => 'fixture/theme', 'dir' => self::fixtures().'/theme/compositions', 'local' => false],
                ['name' => 'theme', 'dir' => $local.'/compositions', 'local' => true],
            ]);
            Compositions::load(self::fixtures().'/module-b/resources/compositions');
            Compositions::load(self::fixtures().'/module-a/resources/compositions');
        });
    }

    /**
     * A file of the local theme, `theme/compositions/<composition>/<handle>.json`.
     *
     * @param  array<string, mixed>|string  $patch
     */
    protected function theme(string $composition, string $handle, array|string $patch): void
    {
        @mkdir($this->local.'/compositions/'.$composition, 0777, true);
        file_put_contents($this->local.'/compositions/'.$composition.'/'.$handle.'.json', is_string($patch) ? $patch : (string) json_encode($patch));
    }

    /**
     * A patch of the site's config, `webx-compositions.patches`.
     *
     * @param  array<string, mixed>  $patch
     */
    protected function config(string $composition, string $handle, array $patch): void
    {
        // Not by a dotted key: a handle has dots of its own.
        $patches = (array) config('webx-compositions.patches', []);
        $patches[$composition][$handle] = $patch;
        config()->set('webx-compositions.patches', $patches);
    }

    /** The page a request gets: a fresh look at the sources, as on the next request. */
    protected function page(?string $handle = null, string $layout = 'layout'): string
    {
        $this->app->forgetScopedInstances();
        $attribute = $handle === null ? '' : " handle=\"{$handle}\"";

        return Blade::render("<x-{$layout}{$attribute}><p>Body</p></x-{$layout}>");
    }

    protected function tree(string $handle = 'default', string $composition = 'layout'): Tree
    {
        return $this->app->make(Compiler::class)->tree($composition, $handle);
    }

    /**
     * The ids of the final tree, depth first, with their parents: `main>content`.
     *
     * @return list<string>
     */
    protected function ids(Tree $tree): array
    {
        $ids = [];

        foreach ($tree->root->walk() as $node) {
            $ids[] = ($node->parent === null ? '' : $node->parent->id.'>').$node->id;
        }

        return $ids;
    }

    /** @return list<string> */
    protected function findings(Tree $tree): array
    {
        return array_map(static fn ($finding): string => (string) $finding, $tree->findings);
    }

    protected static function fixtures(): string
    {
        return str_replace('\\', '/', __DIR__).'/Fixtures';
    }
}
