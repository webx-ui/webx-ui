<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Panel\PackageRegistry;

/**
 * The half of `webx:panel` that reads what is installed.
 *
 * Every test here runs against a fixed list of packages rather than whatever this checkout
 * happens to have in `vendor`, because the interesting cases — a package that declares nothing,
 * one the site has already configured its own way — are about the shape of the list, not about
 * which modules exist today.
 */
final class PanelSyncTest extends TestCase
{
    private Filesystem $files;

    /** @var list<string> */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;

        $this->app->instance(
            PackageRegistry::class,
            new PackageRegistry($this->files, __DIR__.'/Fixtures/installed.json'),
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            $this->files->delete($path);
        }

        $this->files->delete($this->app->configPath('webx-admin.php'));
        $this->files->deleteDirectory($this->app->basePath('resources/js'));
        $this->files->deleteDirectory($this->app->basePath('resources/views/components'));

        parent::tearDown();
    }

    private function at(string $relative): string
    {
        $path = $this->app->basePath($relative);
        $this->written[] = $path;

        return $path;
    }

    private function entry(): string
    {
        return $this->at('resources/js/admin.ts');
    }

    private function readFile(string $path): string
    {
        return (string) $this->files->get($path);
    }

    private function writeFile(string $path, string $contents): void
    {
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents);
    }

    #[Test]
    public function it_wires_every_installed_module_into_the_entry(): void
    {
        $entry = $this->entry();

        $this->artisan('webx:panel')->assertSuccessful();

        $contents = $this->readFile($entry);

        $this->assertStringContainsString("import { pages } from '@webx-ui/module-pages'", $contents);
        $this->assertStringContainsString("import '@webx-ui/module-pages/style.css'", $contents);
        $this->assertStringContainsString('pages(),', $contents);
        $this->assertStringContainsString('seo(),', $contents);
        // Two sections out of one package: the registry lists both calls.
        $this->assertStringContainsString('admins(),', $contents);
        $this->assertStringContainsString('connect(),', $contents);
    }

    #[Test]
    public function a_package_that_declares_nothing_is_simply_not_a_module(): void
    {
        // `nested-set` and `routing` have no npm half at all, and a package written before
        // `extra.webx` existed has to keep installing.
        $entry = $this->entry();

        $this->artisan('webx:panel')->assertSuccessful();

        $contents = $this->readFile($entry);

        $this->assertStringNotContainsString('nested-set', $contents);
        $this->assertStringNotContainsString('no-extra-at-all', $contents);
    }

    #[Test]
    public function running_it_again_changes_nothing(): void
    {
        // The whole point of --sync is that it can be run on a site that is already living its
        // life. A second run that reshuffles the file is one nobody dares to run.
        $entry = $this->entry();

        $this->artisan('webx:panel')->assertSuccessful();
        $first = $this->readFile($entry);

        $this->artisan('webx:panel', ['--sync' => true])->assertSuccessful();

        $this->assertSame($first, $this->readFile($entry));
    }

    #[Test]
    public function sync_leaves_an_existing_entry_where_it_is(): void
    {
        $entry = $this->entry();
        $this->writeFile($entry, $this->handWritten());

        $this->artisan('webx:panel', ['--sync' => true])->assertSuccessful();

        $contents = $this->readFile($entry);

        $this->assertStringContainsString('const resolveAvatar', $contents);
        $this->assertStringContainsString("import { pages } from '@webx-ui/module-pages'", $contents);
    }

    #[Test]
    public function it_leaves_a_module_the_site_configured_its_own_way_alone(): void
    {
        // `seo({ mediaField: WxMediaField })` is the SEO module registered. Matching the whole
        // line would add a second, bare one right under it.
        $entry = $this->entry();
        $this->writeFile($entry, $this->handWritten());

        $this->artisan('webx:panel', ['--sync' => true])->assertSuccessful();

        $contents = $this->readFile($entry);

        $this->assertSame(1, substr_count($contents, 'seo('));
        $this->assertStringContainsString('seo({ mediaField: WxMediaField })', $contents);
    }

    #[Test]
    public function it_folds_an_import_into_the_line_that_is_already_there(): void
    {
        // The entry file with the sign-in plugin in it already imports from module-auth; the
        // sections that package brings are the registry's business. Two import statements for
        // one module compile, but nobody writes them by hand.
        $entry = $this->entry();

        $this->artisan('webx:panel')->assertSuccessful();

        $contents = $this->readFile($entry);

        $this->assertSame(1, substr_count($contents, "from '@webx-ui/module-auth'"));
        $this->assertStringContainsString(
            "import { admins, auth, connect, WxUserMenu } from '@webx-ui/module-auth'",
            $contents,
        );
    }

    #[Test]
    public function erased_markers_are_answered_with_the_lines_to_add(): void
    {
        // Erasing them is a legitimate way of saying the file is the site's now. Everything
        // else the command does still applies, so this is a warning and a success.
        $entry = $this->entry();
        $this->writeFile($entry, "import { createAdmin } from '@webx-ui/module-admin'\n\ncreateAdmin({ modules: [] }).mount()\n");

        $this->artisan('webx:panel', ['--sync' => true])
            ->expectsOutputToContain('markers')
            ->expectsOutputToContain("import { pages } from '@webx-ui/module-pages'")
            ->assertSuccessful();

        $this->assertStringNotContainsString('pages()', $this->readFile($entry));
    }

    #[Test]
    public function an_entry_without_markers_that_has_everything_is_not_nagged_about(): void
    {
        // Nothing is missing, so there is nothing to say: a command that nags on every run
        // about a file it cannot write to is one people stop reading.
        $entry = $this->entry();
        $this->writeFile($entry, <<<'TS'
            import { createAdmin } from '@webx-ui/module-admin'
            import { admins, auth, connect, WxUserMenu } from '@webx-ui/module-auth'
            import { pages } from '@webx-ui/module-pages'
            import { seo } from '@webx-ui/module-seo'

            createAdmin({
              modules: [pages(), seo(), admins(), connect()],
              plugins: [auth()],
              userMenu: WxUserMenu,
            }).mount()
            TS);

        $this->artisan('webx:panel', ['--sync' => true])
            ->doesntExpectOutputToContain('add these by hand')
            ->assertSuccessful();
    }

    #[Test]
    public function it_refuses_to_overwrite_an_entry_but_offers_to_sync_it(): void
    {
        $entry = $this->entry();
        $this->writeFile($entry, '// mine');

        $this->artisan('webx:panel')
            ->expectsOutputToContain('--sync')
            ->assertFailed();

        $this->assertSame('// mine', $this->readFile($entry));
    }

    #[Test]
    public function it_adds_the_npm_halves_to_package_json(): void
    {
        $this->entry();
        $manifest = $this->at('package.json');
        $this->writeFile($manifest, json_encode([
            'private' => true,
            'devDependencies' => ['vite' => '^8.0.0'],
            'dependencies' => ['vue' => '^3.5.42'],
        ], JSON_PRETTY_PRINT)."\n");

        $this->artisan('webx:panel')->assertSuccessful();

        $dependencies = json_decode($this->readFile($manifest), true);

        $this->assertSame('^0.3.10', $dependencies['dependencies']['@webx-ui/module-pages']);
        $this->assertSame('^0.13.0', $dependencies['dependencies']['@webx-ui/module-admin']);
        // A range the site already chose is the site's: this adds what is missing, no more.
        $this->assertSame('^3.5.42', $dependencies['dependencies']['vue']);
        $this->assertSame(['vite' => '^8.0.0'], $dependencies['devDependencies']);
    }

    #[Test]
    public function a_half_kept_under_dev_dependencies_is_not_added_twice(): void
    {
        // Moving somebody's dependency between sections is rude, and npm would then install
        // two ranges of the same package.
        $this->entry();
        $manifest = $this->at('package.json');
        $this->writeFile($manifest, json_encode([
            'devDependencies' => ['@webx-ui/module-pages' => '^0.3.0'],
        ], JSON_PRETTY_PRINT)."\n");

        $this->artisan('webx:panel')->assertSuccessful();

        $dependencies = json_decode($this->readFile($manifest), true);

        $this->assertArrayNotHasKey('@webx-ui/module-pages', $dependencies['dependencies'] ?? []);
        $this->assertSame('^0.3.0', $dependencies['devDependencies']['@webx-ui/module-pages']);
    }

    #[Test]
    public function running_it_again_leaves_package_json_alone(): void
    {
        $this->entry();
        $manifest = $this->at('package.json');
        $this->writeFile($manifest, json_encode(['private' => true], JSON_PRETTY_PRINT)."\n");

        $this->artisan('webx:panel')->assertSuccessful();
        $first = $this->readFile($manifest);

        $this->artisan('webx:panel', ['--sync' => true])->assertSuccessful();

        $this->assertSame($first, $this->readFile($manifest));
    }

    #[Test]
    public function it_says_what_to_install_when_there_is_no_package_json(): void
    {
        $this->entry();

        $this->artisan('webx:panel')
            ->expectsOutputToContain('@webx-ui/module-admin')
            ->assertSuccessful();
    }

    #[Test]
    public function it_asks_for_the_sign_in_plugin_when_the_entry_predates_it(): void
    {
        // A file written before the auth package was installed gets the sections it brings and
        // no way to reach them, and a panel that redirects to nowhere says nothing about why.
        $entry = $this->entry();
        $this->writeFile($entry, $this->handWritten());

        $this->artisan('webx:panel', ['--sync' => true])
            ->expectsOutputToContain('plugins: [auth()]')
            ->assertSuccessful();
    }

    #[Test]
    public function it_stands_the_public_pages_of_a_module_in_the_site_layout(): void
    {
        $this->withLayoutSeam();
        $config = $this->at('config/webx-pages.php');
        $this->writeFile($config, $this->publishedConfig());
        $this->writeFile($this->at('resources/views/components/layout.blade.php'), '<html>{{ $slot }}</html>');

        $this->artisan('webx:panel')->assertSuccessful();

        $this->assertStringContainsString("'layout' => env('WEBX_PAGES_LAYOUT', 'layout')", $this->readFile($config));
    }

    #[Test]
    public function a_site_without_a_layout_keeps_the_module_printing_its_own_document(): void
    {
        // The seam is the site's to open. Pointing a module at `<x-layout>` that nobody wrote
        // is a blog that answers with an exception instead of a bare page.
        $this->withLayoutSeam();
        $config = $this->at('config/webx-pages.php');
        $this->writeFile($config, $this->publishedConfig());

        $this->artisan('webx:panel')->assertSuccessful();

        $this->assertSame($this->publishedConfig(), $this->readFile($config));
    }

    #[Test]
    public function a_layout_the_site_chose_is_left_alone(): void
    {
        $this->withLayoutSeam('shell');
        $config = $this->at('config/webx-pages.php');
        $this->writeFile($config, str_replace("env('WEBX_PAGES_LAYOUT')", "'shell'", $this->publishedConfig()));
        $this->writeFile($this->at('resources/views/components/layout.blade.php'), '<html>{{ $slot }}</html>');

        $this->artisan('webx:panel')->assertSuccessful();

        $this->assertStringContainsString("'layout' => 'shell'", $this->readFile($config));
    }

    #[Test]
    public function running_it_again_leaves_the_layout_alone(): void
    {
        // The second run is the one that matters: the configuration was published by the first
        // and is not in `config()` of this process, so only the file itself can say it is done.
        $this->withLayoutSeam();
        $config = $this->at('config/webx-pages.php');
        $this->writeFile($config, $this->publishedConfig());
        $this->writeFile($this->at('resources/views/components/layout.blade.php'), '<html>{{ $slot }}</html>');

        $this->artisan('webx:panel')->assertSuccessful();
        $after = $this->readFile($config);

        $this->artisan('webx:panel', ['--sync' => true])->assertSuccessful();

        $this->assertSame($after, $this->readFile($config));
    }

    /**
     * `module-pages` as installed: a module whose configuration carries a layout key, which is
     * the whole of how a package says it has a public half to stand somewhere.
     */
    private function withLayoutSeam(?string $layout = null): void
    {
        config()->set('webx-pages', ['view' => 'pages.show', 'layout' => $layout]);
    }

    private function publishedConfig(): string
    {
        return "<?php\n\nreturn [\n\n    'view' => env('WEBX_PAGES_VIEW', 'pages.show'),\n\n    'layout' => env('WEBX_PAGES_LAYOUT'),\n\n];\n";
    }

    /** An entry file the way a site ends up writing one: markers kept, everything else its own. */
    private function handWritten(): string
    {
        return <<<'TS'
            import { createAdmin } from '@webx-ui/module-admin'
            import { createMediaApi, WxMediaField } from '@webx-ui/module-media'
            // webx:imports
            import { seo } from '@webx-ui/module-seo'
            // /webx:imports

            import '@webx-ui/core/style.css'
            // webx:styles
            import '@webx-ui/module-seo/style.css'
            // /webx:styles

            const resolveAvatar = async (key: string) =>
              (await createMediaApi(panel.context).fileByPath(key))?.url ?? null

            const panel = createAdmin({
              basePath: '/cms',
              modules: [
                // webx:modules
                seo({ mediaField: WxMediaField }),
                // /webx:modules
              ],
            })

            panel.mount()
            TS;
    }
}
