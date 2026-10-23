<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClassConstant;
use WebxUi\Admin\Console\DoctorCommand;
use WebxUi\Admin\Doctor\Checks\Halves;
use WebxUi\Admin\Doctor\Checks\Helpers;
use WebxUi\Admin\Doctor\Checks\Languages;
use WebxUi\Admin\Doctor\Checks\Layouts;
use WebxUi\Admin\Doctor\Checks\Methods;
use WebxUi\Admin\Doctor\Checks\NpmRanges;
use WebxUi\Admin\Doctor\Checks\PanelOpens;
use WebxUi\Admin\Doctor\Checks\Queue;
use WebxUi\Admin\Doctor\Checks\Regions;
use WebxUi\Admin\Doctor\Checks\TrustedProxies;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\Doctor\VersionRange;
use WebxUi\Admin\Http\Controllers\MethodProbeController;
use WebxUi\Admin\Panel\PackageRegistry;
use WebxUi\Admin\Tests\Fixtures\FixedCheck;
use WebxUi\Compositions\Compositions;
use WebxUi\Compositions\CompositionsServiceProvider;

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

    // -- queue -------------------------------------------------------------------------------

    #[Test]
    public function it_says_when_the_database_queue_has_no_worker(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default']]);
        Schema::create('jobs', static function (Blueprint $table): void {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        $job = static fn (int $ago, ?int $reserved = null): array => [
            'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
            'reserved_at' => $reserved, 'available_at' => time() - $ago, 'created_at' => time() - $ago,
        ];

        // Due a minute ago, delayed into the future, taken by a worker: none of them is a sign.
        DB::table('jobs')->insert([$job(60), $job(-3600), $job(3600, time())]);
        $this->assertSame([Diagnosis::OK], array_map(static fn (Diagnosis $d): string => $d->state, $this->app->make(Queue::class)->run()));

        DB::table('jobs')->insert([$job(600), $job(3600)]);
        $found = $this->app->make(Queue::class)->run();

        $this->assertSame(Diagnosis::WARN, $found[0]->state);
        $this->assertStringContainsString('2 jobs have waited more than 5 minutes', $found[0]->detail);
        $this->assertStringContainsString('queue:work --stop-when-empty', $found[0]->detail);

        Schema::drop('jobs');
        $this->assertSame(Diagnosis::FAIL, $this->app->make(Queue::class)->run()[0]->state);
    }

    // -- trusted proxies ---------------------------------------------------------------------

    #[Test]
    public function trusting_every_address_as_a_proxy_is_a_warning(): void
    {
        $found = function (mixed $config, mixed $bootstrap = null): Diagnosis {
            $this->app['config']->set('trustedproxy.proxies', $config);
            TrustProxies::flushState();

            if ($bootstrap !== null) {
                TrustProxies::at($bootstrap);
            }

            return $this->app->make(TrustedProxies::class)->run()[0];
        };

        try {
            $states = [
                $found(['127.0.0.0/8', '10.0.0.0/8', 'fc00::/7'])->state,
                $found('*')->state,
                $found('10.0.0.0/8, 0.0.0.0/0')->state,
                // `trustProxies(at: ...)` in bootstrap/app.php wins over the config, here as in the middleware.
                $found(['10.0.0.0/8'], '*')->state,
            ];

            $this->assertSame([Diagnosis::OK, Diagnosis::WARN, Diagnosis::WARN, Diagnosis::WARN], $states);
            $this->assertStringContainsString('TRUSTED_PROXIES', $found('*')->detail);
        } finally {
            TrustProxies::flushState();
        }
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
    public function it_tells_a_range_that_reaches_a_version_from_one_that_never_will(): void
    {
        // Below 1.0 the caret pins the minor: `^0.30.0` never installs 0.31.
        $this->assertFalse(VersionRange::admits('^0.30.0', '0.31.0'));
        $this->assertTrue(VersionRange::admits('^0.31.0', '0.31.0'));
        $this->assertTrue(VersionRange::admits('^3.4.0', '3.5.0'));
        $this->assertFalse(VersionRange::admits('^3.4.0', '4.0.0'));
        $this->assertFalse(VersionRange::admits('^0.0.3', '0.0.4'));
        $this->assertFalse(VersionRange::admits('~1.2.0', '1.3.0'));
        $this->assertTrue(VersionRange::admits('1.x', '1.9.0'));
        $this->assertFalse(VersionRange::admits('1.2', '1.3.0'));
        $this->assertFalse(VersionRange::admits('0.30.1', '0.31.0'));
        $this->assertTrue(VersionRange::admits('>=0.30.0', '0.31.0'));
        $this->assertFalse(VersionRange::admits('>=0.30.0 <0.31.0', '0.31.0'));
        $this->assertTrue(VersionRange::admits('>=0.30.0 <=0.31.0', '0.31.0'));

        // What is not understood, or names a place, is left to the site.
        $this->assertTrue(VersionRange::admits('^0.29.0 || ^0.30.0', '0.31.0'));
        $this->assertTrue(VersionRange::admits('file:../webx-ui/packages/module-admin', '0.31.0'));
    }

    #[Test]
    public function a_range_that_cannot_reach_the_half_points_at_the_sync(): void
    {
        $this->packageJson(['@webx-ui/module-pages' => '^0.2.0', '@webx-ui/module-auth' => '^0.8.0', '@webx-ui/module-seo' => '^0.4.2']);

        $details = $this->details((new NpmRanges($this->app, $this->files, $this->packages()))->run());

        $this->assertStringContainsString('webx:panel --sync', $details);
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
    public function a_shared_block_is_not_looked_for_in_the_layout(): void
    {
        $this->layout("<html><head>@stack('head')</head><body>\n<x-webx-blocks::region name=\"header\" />\n{{ \$slot }}</body></html>\n");
        $this->app['config']->set('webx-pages.layout', 'layout');
        $this->app['config']->set('webx-blocks.regions', ['header' => ['title' => 'Header'], 'request' => ['title' => 'Request', 'fragment' => true]]);

        $found = $this->regions();

        $this->assertCount(1, $found, $this->details($found));
        $this->assertSame(Diagnosis::OK, $found[0]->state);
        $this->assertStringContainsString('[header]', $found[0]->detail);
    }

    #[Test]
    public function it_says_nothing_about_regions_when_none_are_declared(): void
    {
        $this->layout("<html><head>@stack('head')</head><body>{{ \$slot }}</body></html>\n");
        $this->app['config']->set('webx-pages.layout', 'layout');

        $this->assertSame([], $this->regions());
    }

    /**
     * theme-default's layout is a dispatcher of the composition `layout` (COMPOSITIONS §6.1): the
     * seams are in the compiled view, and that is what the two checks read.
     */
    #[Test]
    public function a_layout_that_is_a_composition_is_read_as_its_compiled_view(): void
    {
        $root = sys_get_temp_dir().'/webx-doctor-composition-'.bin2hex(random_bytes(6));
        $this->directories[] = $root;
        $this->files->ensureDirectoryExists($root.'/resources/compositions/layout');
        $this->files->ensureDirectoryExists($root.'/blocks');
        $this->files->put($root.'/blocks/region.blade.php', "<div></div>\n");
        $this->files->put($root.'/resources/compositions/layout/default.json', (string) json_encode(['root' => [
            'id' => 'html', 'tag' => 'html', 'children' => [
                ['id' => 'head', 'tag' => 'head', 'children' => [
                    ['id' => 'head.slot', 'slot' => 'head'],
                    ['id' => 'head.stack', 'stack' => 'head'],
                ]],
                ['id' => 'body', 'tag' => 'body', 'children' => [
                    ['id' => 'header', 'region' => 'header'],
                    ['id' => 'content', 'slot' => 'default'],
                ]],
            ],
        ]]));

        $this->app->register(CompositionsServiceProvider::class);
        $this->app['config']->set('webx-compositions.compiled', $root.'/compiled');
        Blade::anonymousComponentPath($root.'/blocks', 'webx-blocks');
        Compositions::load($root.'/resources/compositions', 'fixture');

        $this->layout("@include(composition_view('layout', \$attributes->get('handle')))\n");
        $this->app['config']->set('webx-pages.layout', 'layout');
        $this->app['config']->set('webx-blocks.regions', ['header' => ['title' => 'Header'], 'footer' => ['title' => 'Footer']]);

        $this->assertSame(Diagnosis::OK, $this->layouts()[0]->state, $this->details($this->layouts()));

        $regions = $this->regions();
        $this->assertSame(Diagnosis::OK, $regions[0]->state, $this->details($regions));
        $this->assertTrue($regions[1]->warned());
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
                cache(),
                // /webx:modules
              ],
            })
            TS);

        $details = $this->details($this->halves());

        $this->assertStringNotContainsString('installed on the server and not in', $details);
    }

    #[Test]
    public function a_section_of_the_frame_without_its_client_half_is_red(): void
    {
        // «System → Cache»: the server half is module-admin itself, so the manifest lists the
        // module on every site, and a panel without `cache()` answers its address with nothing.
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
                // /webx:modules
                // cache(),
              ],
            })
            TS);

        $found = $this->halves();

        $this->assertTrue($found[0]->failed());
        $this->assertStringContainsString('webx-ui/module-admin (cache())', $this->details($found));
    }

    #[Test]
    public function a_call_written_in_by_hand_outside_the_markers_is_wired(): void
    {
        // The way sites registered the cache screen before module-admin declared it.
        $this->entry(<<<'TS'
            import { cache, createAdmin } from '@webx-ui/module-admin'
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
                // /webx:modules
                cache(),
              ],
            })
            TS);

        $details = $this->details($this->halves());

        $this->assertStringNotContainsString('installed on the server and not in', $details);
        $this->assertStringNotContainsString('no installed package claims', $details);
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

    // -- PUT and DELETE ----------------------------------------------------------------------

    #[Test]
    public function the_probe_answers_put_and_delete_with_its_mark(): void
    {
        $this->put('/api/cms/method-probe')->assertOk()->assertHeader(MethodProbeController::HEADER, 'PUT');
        $this->delete('/api/cms/method-probe')->assertOk()->assertHeader(MethodProbeController::HEADER, 'DELETE');
        $this->get('/api/cms/method-probe')->assertStatus(405);
    }

    #[Test]
    public function it_is_quiet_when_laravel_answers_put_and_delete(): void
    {
        config(['app.url' => 'https://site.test']);
        Http::fake(static fn (Request $request) => Http::response(
            ['method' => $request->method()],
            200,
            [MethodProbeController::HEADER => $request->method()],
        ));

        $found = $this->app->make(Methods::class)->run();

        $this->assertSame([Diagnosis::OK], array_map(static fn (Diagnosis $d): string => $d->state, $found));
        $this->assertStringContainsString('passes them on', $this->details($found));
        Http::assertSent(static fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://site.test/api/cms/method-probe');
        Http::assertSent(static fn (Request $request): bool => $request->method() === 'DELETE');
    }

    #[Test]
    public function it_warns_when_the_web_server_refuses_put_before_laravel(): void
    {
        config(['app.url' => 'https://site.test']);
        Http::fake(['*' => Http::response('<html>405 Not Allowed</html>', 405)]);

        $found = $this->app->make(Methods::class)->run();

        $this->assertSame([Diagnosis::WARN], array_map(static fn (Diagnosis $d): string => $d->state, $found));
        $this->assertStringContainsString('PUT 405', $this->details($found));
        $this->assertStringContainsString('methodOverride: true', $this->details($found));
    }

    #[Test]
    public function a_refusal_laravel_marked_is_not_the_web_servers(): void
    {
        config(['app.url' => 'https://site.test']);
        // A 405 that carries the mark came from Laravel, which did see the method.
        Http::fake(static fn (Request $request) => Http::response('', 405, [MethodProbeController::HEADER => $request->method()]));

        $this->assertSame(Diagnosis::OK, $this->app->make(Methods::class)->run()[0]->state);
    }

    #[Test]
    public function it_says_the_override_is_already_on_when_the_entry_asks_for_it(): void
    {
        config(['app.url' => 'https://site.test', 'webx-admin.vite' => ['resources/js/doctor-methods.js']]);
        $this->write($this->app->basePath('resources/js/doctor-methods.js'), "createAdmin({ modules, methodOverride: true })\n");
        Http::fake(['*' => Http::response('', 403)]);

        $found = $this->app->make(Methods::class)->run();

        $this->assertSame(Diagnosis::OK, $found[0]->state);
        $this->assertStringContainsString('resources/js/doctor-methods.js', $this->details($found));
    }

    #[Test]
    public function it_skips_the_check_when_the_site_cannot_be_reached(): void
    {
        config(['app.url' => 'https://site.test']);
        Http::fake(['*' => Http::failedConnection()]);

        $found = $this->app->make(Methods::class)->run();

        $this->assertSame(Diagnosis::OK, $found[0]->state);
        $this->assertStringContainsString('not checked', $this->details($found));
    }

    #[Test]
    public function it_does_not_guess_from_an_answer_that_is_not_a_refusal(): void
    {
        config(['app.url' => 'http://site.test']);
        Http::fake(['*' => Http::response('', 301, ['Location' => 'https://site.test/api/cms/method-probe'])]);

        $found = $this->app->make(Methods::class)->run();

        $this->assertSame(Diagnosis::OK, $found[0]->state);
        $this->assertStringContainsString('answered 301', $this->details($found));
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

    #[Test]
    public function a_strict_deploy_does_not_stop_on_a_note_about_the_content(): void
    {
        $this->findings(Diagnosis::content('Regions', 'The shared block [request] is placed on no page.'));

        $this->artisan('webx:doctor', ['--strict' => true])
            ->expectsOutputToContain('(content)')
            ->expectsOutputToContain('1 note on the content — mended in the panel.')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_strict_deploy_stops_on_a_warning_about_the_setup(): void
    {
        $this->findings(
            Diagnosis::content('Regions', 'The shared block [request] is placed on no page.'),
            Diagnosis::warn('Regions', 'The region [footer] is declared, but the layout does not print it.'),
        );

        $this->artisan('webx:doctor', ['--strict' => true])
            ->expectsOutputToContain('1 worth mentioning, and --strict says that is enough to stop. 1 note on the content')
            ->assertExitCode(1);

        $this->artisan('webx:doctor')->assertExitCode(0);
    }

    #[Test]
    public function a_strict_deploy_does_not_stop_on_a_note(): void
    {
        $this->findings(Diagnosis::note('Snapshots', '1 in storage/app/snapshots, 48 MB, the oldest from 2026-01-01.'));

        $this->artisan('webx:doctor', ['--strict' => true])
            ->expectsOutputToContain('(note) Snapshots: 1 in storage/app/snapshots')
            ->expectsOutputToContain('Everything this site needs is in place. 1 note to know about.')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_note_does_not_cover_a_failure_or_a_warning(): void
    {
        $this->findings(
            Diagnosis::note('Snapshots', '1 in storage/app/snapshots.'),
            Diagnosis::warn('Queue', 'jobs wait and nobody takes them.'),
        );

        $this->artisan('webx:doctor', ['--strict' => true])
            ->expectsOutputToContain('1 worth mentioning, and --strict says that is enough to stop. 1 note to know about.')
            ->assertExitCode(1);

        $this->findings(
            Diagnosis::note('Snapshots', '1 in storage/app/snapshots.'),
            Diagnosis::fail('Storage', 'run php artisan storage:link'),
        );

        $this->assertSame(1, Artisan::call('webx:doctor', ['--strict' => true, '--json' => true]));
        $this->assertFalse(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['ok']);
    }

    #[Test]
    public function a_strict_deploy_stops_on_a_failure(): void
    {
        $this->findings(Diagnosis::fail('Storage', 'run php artisan storage:link'));

        $this->artisan('webx:doctor', ['--strict' => true])->assertExitCode(1);
        $this->artisan('webx:doctor')->assertExitCode(1);
    }

    #[Test]
    public function it_prints_the_findings_as_json(): void
    {
        $this->findings(
            Diagnosis::ok('Storage', 'public/storage points at the library.'),
            Diagnosis::warn('Queue', 'jobs wait and nobody takes them.'),
            Diagnosis::content('Regions', 'The shared block [request] is placed on no page.'),
            Diagnosis::note('Snapshots', '1 in storage/app/snapshots.'),
        );

        $code = Artisan::call('webx:doctor', ['--json' => true, '--strict' => true]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $code);
        $this->assertSame([
            'ok' => false,
            'checks' => [
                ['state' => 'ok', 'level' => 'ok', 'subject' => 'Storage', 'detail' => 'public/storage points at the library.', 'content' => false],
                ['state' => 'warn', 'level' => 'warn', 'subject' => 'Queue', 'detail' => 'jobs wait and nobody takes them.', 'content' => false],
                ['state' => 'warn', 'level' => 'content', 'subject' => 'Regions', 'detail' => 'The shared block [request] is placed on no page.', 'content' => true],
                ['state' => 'warn', 'level' => 'note', 'subject' => 'Snapshots', 'detail' => '1 in storage/app/snapshots.', 'content' => false],
            ],
            'failed' => 0,
            'warned' => 1,
            'content' => 1,
            'notes' => 1,
        ], $report);

        // Without --strict the same report passes, and says so.
        $this->assertSame(0, Artisan::call('webx:doctor', ['--json' => true]));
        $this->assertTrue(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['ok']);
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

    /**
     * The command with every check of its own and of the modules quiet, and one that finds these.
     */
    private function findings(Diagnosis ...$found): void
    {
        /** @var list<class-string> $checks */
        $checks = (new ReflectionClassConstant(DoctorCommand::class, 'CHECKS'))->getValue();

        foreach ([...$checks, ...$this->app->make(DoctorChecks::class)->all()] as $check) {
            $this->app->instance($check, new FixedCheck);
        }

        $this->app->make(DoctorChecks::class)->register(FixedCheck::class);
        $this->app->instance(FixedCheck::class, new FixedCheck(array_values($found)));
    }

    /** @param list<Diagnosis> $found */
    private function details(array $found): string
    {
        return implode("\n", array_map(static fn (Diagnosis $d): string => $d->subject.': '.$d->detail, $found));
    }
}
