<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Checks\Halves;
use WebxUi\Admin\Doctor\Checks\Helpers;
use WebxUi\Admin\Doctor\Checks\Languages;
use WebxUi\Admin\Doctor\Checks\Layouts;
use WebxUi\Admin\Doctor\Checks\NpmRanges;
use WebxUi\Admin\Doctor\Checks\PanelOpens;
use WebxUi\Admin\Doctor\Checks\Regions;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\VersionRange;
use WebxUi\Admin\Panel\PackageRegistry;

/**
 * What `webx:doctor` says, for the three sorts of breakage that cost the most to find by hand.
 *
 * Whether the command *runs* on a real site is the smoke script's question — half of what it
 * looks at is a real database, a real build and a real MariaDB. What is here is the deciding:
 * given an entry file, a `package.json` and a layout, does it say the right thing about them.
 */
final class DoctorTest extends TestCase
{
    private Filesystem $files;

    /** @var list<string> */
    private array $written = [];

    /** @var list<string> */
    private array $directories = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            $this->files->delete($path);
        }

        foreach ($this->directories as $path) {
            $this->files->deleteDirectory($path);
        }

        parent::tearDown();
    }

    // -- npm ranges --------------------------------------------------------------------------

    #[Test]
    public function it_knows_the_template_helpers_are_the_packages_own(): void
    {
        $found = $this->app->make(Helpers::class)->run();

        $this->assertSame(['banners()', 'banners_layout()', 'events()', 'menu()', 'press()', 'recipes()', 'reviews()', 'services()', 'tariffs()', 'team()', 'vacancies()'], array_map(static fn (Diagnosis $diagnosis): string => $diagnosis->subject, $found));
        $this->assertSame(array_fill(0, 11, Diagnosis::OK), array_map(static fn (Diagnosis $diagnosis): string => $diagnosis->state, $found));
    }

    #[Test]
    public function it_reads_the_floor_out_of_a_range(): void
    {
        $this->assertSame('0.19.0', VersionRange::floor('^0.19.0'));
        $this->assertSame('1.2.0', VersionRange::floor('~1.2'));
        $this->assertSame('1.0.0', VersionRange::floor('>=1.0.0 <2.0.0'));
        $this->assertSame('3.0.0', VersionRange::floor('3.x'));

        // A range that names a place has no floor, and that is how the monorepo's own demo
        // runs: the site points at a checkout and has answered the question itself.
        $this->assertNull(VersionRange::floor('file:../webx-ui.local/packages/core'));
        $this->assertNull(VersionRange::floor('workspace:^'));
    }

    #[Test]
    public function it_says_which_npm_half_is_a_minor_too_old(): void
    {
        // The caret pins the minor below 1.0, so a site asking a minor too low never reaches
        // the version the server needs — and finds out as a missing export in a build.
        $this->packageJson(['@webx-ui/module-pages' => '^0.2.0', '@webx-ui/module-auth' => '^0.8.0', '@webx-ui/module-seo' => '^0.4.2']);

        $details = $this->details((new NpmRanges($this->app, $this->files, $this->packages()))->run());

        $this->assertStringContainsString('@webx-ui/module-pages@^0.2.0', $details);
        $this->assertStringContainsString('^0.3.10', $details);
        $this->assertStringContainsString('npm install', $details);
    }

    #[Test]
    public function it_says_which_npm_half_is_not_asked_for_at_all(): void
    {
        $this->packageJson(['@webx-ui/module-pages' => '^0.3.10']);

        $details = $this->details((new NpmRanges($this->app, $this->files, $this->packages()))->run());

        $this->assertStringContainsString('does not ask for @webx-ui/module-auth', $details);
        $this->assertStringContainsString('webx:panel --sync', $details);
    }

    #[Test]
    public function it_is_quiet_when_every_half_is_new_enough(): void
    {
        $this->packageJson([
            '@webx-ui/core' => '^0.30.0',
            '@webx-ui/module-admin' => '^0.13.0',
            '@webx-ui/module-auth' => '^0.8.0',
            '@webx-ui/module-pages' => '^0.4.0',
            '@webx-ui/module-seo' => '^0.4.2',
            '@webx-ui/tokens' => '^0.4.0',
            'vue' => '^3.5.0',
            'vue-router' => '^4.5.0',
        ]);

        $found = (new NpmRanges($this->app, $this->files, $this->packages()))->run();

        $this->assertSame([Diagnosis::OK], array_values(array_unique(array_map(
            static fn (Diagnosis $diagnosis): string => $diagnosis->state,
            $found,
        ))));
    }

    // -- the layout seam ---------------------------------------------------------------------

    #[Test]
    public function it_catches_a_layout_without_the_head_stack(): void
    {
        // The worst of the three, because nothing looks wrong: the page is finished and every
        // metatag a block pushed has been dropped on the way.
        $this->layout("<!doctype html>\n<html><head>{{ \$head ?? '' }}</head><body>{{ \$slot }}</body></html>\n");
        $this->app['config']->set('webx-pages.layout', 'layout');

        $found = $this->layouts();

        $this->assertTrue($found[0]->failed());
        $this->assertStringContainsString("@stack('head')", $found[0]->detail);
    }

    #[Test]
    public function it_catches_a_layout_that_is_not_there(): void
    {
        $this->app['config']->set('webx-pages.layout', 'nothing-like-this');

        $found = $this->layouts();

        $this->assertTrue($found[0]->failed());
        $this->assertStringContainsString('answers 500', $found[0]->detail);
    }

    #[Test]
    public function it_is_content_with_a_layout_that_takes_both(): void
    {
        $this->layout("<html><head>{{ \$head ?? '' }}\n@stack('head')</head><body>{{ \$slot }}</body></html>\n");
        $this->app['config']->set('webx-pages.layout', 'layout');

        $found = $this->layouts();

        $this->assertSame(Diagnosis::OK, $found[0]->state);
    }

    #[Test]
    public function it_mentions_a_module_left_printing_its_own_document(): void
    {
        $this->app['config']->set('webx-pages.layout', '');

        $found = $this->layouts();

        $this->assertTrue($found[0]->warned());
        $this->assertStringContainsString('webx:panel --sync', $found[0]->detail);
    }

    // -- the regions of the layout -------------------------------------------------------------

    #[Test]
    public function it_warns_about_a_region_the_layout_does_not_print(): void
    {
        $this->layout("<html><head>{{ \$head ?? '' }}@stack('head')</head><body>\n<x-webx-blocks::region name=\"header\" fallback=\"components.header\" />\n{{ \$slot }}</body></html>\n");
        $this->app['config']->set('webx-pages.layout', 'layout');
        $this->app['config']->set('webx-blocks.regions', ['header' => ['title' => 'Header'], 'footer' => ['title' => 'Footer']]);

        $found = $this->regions();

        $this->assertCount(2, $found, $this->details($found));
        $this->assertSame(Diagnosis::OK, $found[0]->state);
        $this->assertTrue($found[1]->warned());
        $this->assertStringContainsString('[footer]', $found[1]->detail);
    }

    #[Test]
    public function it_says_nothing_about_regions_when_none_are_declared(): void
    {
        $this->layout("<html><head>@stack('head')</head><body>{{ \$slot }}</body></html>\n");
        $this->app['config']->set('webx-pages.layout', 'layout');

        $this->assertSame([], $this->regions());
    }

    // -- both halves -------------------------------------------------------------------------

    #[Test]
    public function it_names_the_package_the_entry_file_never_heard_of(): void
    {
        $this->entry(<<<'TS'
            // webx:imports
            import { pages } from '@webx-ui/module-pages'
            // /webx:imports
            // webx:styles
            // /webx:styles
            createAdmin({
              modules: [
                // webx:modules
                pages(),
                // /webx:modules
              ],
            })
            TS);

        $details = $this->details($this->halves());

        $this->assertStringContainsString('webx-ui/module-auth', $details);
        $this->assertStringContainsString('webx-ui/module-seo', $details);
        $this->assertStringNotContainsString('webx-ui/module-pages (pages())', $details);
        $this->assertStringContainsString('webx:panel --sync', $details);
    }

    #[Test]
    public function it_counts_a_registration_wired_whatever_arguments_it_was_given(): void
    {
        // `seo({ mediaField: WxMediaField })` is the SEO module registered, and a site that
        // hands a module something is the ordinary case rather than the strange one.
        $this->entry(<<<'TS'
            // webx:imports
            // /webx:imports
            // webx:styles
            // /webx:styles
            createAdmin({
              modules: [
                // webx:modules
                pages(),
                admins(),
                connect(),
                seo({ mediaField: WxMediaField }),
                // /webx:modules
              ],
            })
            TS);

        $details = $this->details($this->halves());

        $this->assertStringNotContainsString('installed on the server and not in', $details);
    }

    #[Test]
    public function it_mentions_a_registration_with_no_package_behind_it(): void
    {
        $this->entry(<<<'TS'
            // webx:imports
            // /webx:imports
            // webx:styles
            // /webx:styles
            createAdmin({
              modules: [
                // webx:modules
                pages(),
                admins(),
                connect(),
                seo(),
                shop(),
                // /webx:modules
              ],
            })
            TS);

        $details = $this->details($this->halves());

        $this->assertStringContainsString('shop()', $details);
        $this->assertStringContainsString('no installed package claims that', $details);
    }

    // -- languages ---------------------------------------------------------------------------

    #[Test]
    public function it_catches_a_fallback_the_site_does_not_publish_in(): void
    {
        $this->app['config']->set('webx-localization.locales', [['code' => 'ru', 'default' => true]]);
        $this->app['config']->set('webx-localization.fallback', 'de');

        $details = $this->details($this->app->make(Languages::class)->run());

        $this->assertStringContainsString('[de]', $details);
        $this->assertStringContainsString('falls back to nothing', $details);
    }

    // -- caches and limiters -----------------------------------------------------------------

    #[Test]
    public function it_catches_a_throttle_nobody_declared(): void
    {
        // Laravel reads an undeclared name as a *number* of attempts, which is zero, so the
        // address answers 429 to everybody — and only ever after `route:cache`.
        $this->app['router']->get('somewhere', static fn (): string => 'ok')
            ->middleware('throttle:webx-nobody-declared-this');

        $details = $this->details($this->app->make(PanelOpens::class)->run());

        $this->assertStringContainsString('webx-nobody-declared-this', $details);
        $this->assertStringContainsString('429', $details);
    }

    // -- the command itself ------------------------------------------------------------------

    #[Test]
    public function it_fails_the_deploy_when_something_is_not_in_place(): void
    {
        $this->app['config']->set('webx-admin.vite', []);

        $this->artisan('webx:doctor')
            ->assertExitCode(1)
            ->expectsOutputToContain('webx:panel --sync');
    }

    // -- bundle ------------------------------------------------------------------------------

    #[Test]
    public function it_trusts_the_content_of_a_bundle_older_than_its_sources(): void
    {
        // Docker's layer cache: the build stage is an hour old, the sources were copied now.
        $this->entry("import './theme'\n");
        $this->write($this->app->basePath('resources/js/theme.ts'), "export const theme = 'dark'\n");
        $this->built(['resources/js/admin.ts', 'resources/js/theme.ts'], time() - 3600);

        $this->assertStringContainsString('Bundle: built from what is on disk now — 2 files', $this->details($this->halves()));
    }

    #[Test]
    public function it_names_the_imported_file_that_changed_after_the_build(): void
    {
        $this->entry("import './theme'\n");
        $this->write($this->app->basePath('resources/js/theme.ts'), "export const theme = 'dark'\n");
        $this->built(['resources/js/admin.ts', 'resources/js/theme.ts'], time() + 3600);

        $this->files->put($this->app->basePath('resources/js/theme.ts'), "export const theme = 'light'\n");

        $this->assertStringContainsString(
            'Bundle: resources/js/theme.ts has changed since the bundle was built',
            $this->details($this->halves()),
        );
    }

    #[Test]
    public function it_catches_a_source_the_bundle_was_built_from_and_that_is_gone(): void
    {
        $this->entry("import './theme'\n");
        $this->write($this->app->basePath('resources/js/theme.ts'), "export const theme = 'dark'\n");
        $this->built(['resources/js/admin.ts', 'resources/js/theme.ts']);

        $this->files->delete($this->app->basePath('resources/js/theme.ts'));

        $this->assertStringContainsString('Bundle: resources/js/theme.ts is gone', $this->details($this->halves()));
    }

    #[Test]
    public function it_falls_back_to_times_when_the_build_left_no_record(): void
    {
        // A site whose vite.config.js predates the plugin.
        $this->entry("createAdmin({})\n");
        $this->built(null, time() - 3600);

        $this->assertStringContainsString(
            'Bundle: resources/js/admin.ts has changed since the bundle was built',
            $this->details($this->halves()),
        );
    }

    // -- scaffolding -------------------------------------------------------------------------

    /**
     * A build of the entry: the manifest, the file it names and, unless null, the record of the
     * sources hashed as they are now.
     *
     * @param  list<string>|null  $sources
     */
    private function built(?array $sources, ?int $at = null): void
    {
        $build = $this->app->publicPath('build');
        $this->files->ensureDirectoryExists($build.'/assets');
        $this->directories[] = $build;

        $this->files->put($build.'/manifest.json', (string) json_encode([
            'resources/js/admin.ts' => ['file' => 'assets/admin.js', 'src' => 'resources/js/admin.ts', 'isEntry' => true],
        ]));
        $this->files->put($build.'/assets/admin.js', 'console.log(1)');
        touch($build.'/assets/admin.js', $at ?? time());

        if ($sources !== null) {
            $files = [];

            foreach ($sources as $name) {
                $files[$name] = (string) hash_file('sha256', $this->app->basePath($name));
            }

            $this->files->put($build.'/webx-sources.json', (string) json_encode(['algorithm' => 'sha256', 'files' => $files]));
        }
    }

    private function packages(): PackageRegistry
    {
        return new PackageRegistry($this->files, __DIR__.'/Fixtures/installed.json');
    }

    /** @return list<Diagnosis> */
    private function halves(): array
    {
        $this->app['config']->set('webx-admin.vite', ['resources/js/admin.ts']);

        return (new Halves($this->app, $this->files, $this->app['config'], $this->packages()))->run();
    }

    /** @return list<Diagnosis> */
    private function layouts(): array
    {
        return (new Layouts(
            $this->app,
            $this->app['config'],
            $this->files,
            $this->app['view'],
            $this->packages(),
        ))->run();
    }

    /** @return list<Diagnosis> */
    private function regions(): array
    {
        return (new Regions($this->app['config'], $this->files, $this->app['view'], $this->packages()))->run();
    }

    /** @param array<string, string> $dependencies */
    private function packageJson(array $dependencies): void
    {
        $this->write(
            $this->app->basePath('package.json'),
            (string) json_encode(['dependencies' => $dependencies], JSON_PRETTY_PRINT),
        );
    }

    private function entry(string $contents): void
    {
        $this->files->ensureDirectoryExists($this->app->basePath('resources/js'));
        $this->write($this->app->basePath('resources/js/admin.ts'), $contents);
    }

    private function layout(string $contents): void
    {
        $this->files->ensureDirectoryExists($this->app->resourcePath('views/components'));
        $this->write($this->app->resourcePath('views/components/layout.blade.php'), $contents);
    }

    private function write(string $path, string $contents): void
    {
        $this->files->put($path, $contents);
        $this->written[] = $path;
    }

    /** @param list<Diagnosis> $found */
    private function details(array $found): string
    {
        return implode("\n", array_map(static fn (Diagnosis $d): string => $d->subject.': '.$d->detail, $found));
    }
}
