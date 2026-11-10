<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClassConstant;
use WebxUi\Admin\Console\DoctorCommand;
use WebxUi\Admin\Doctor\Checks\SiteModules;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Panel\PackageRegistry;
use WebxUi\Admin\Setup\Catalogue;
use WebxUi\Admin\Tests\Fixtures\FixedCheck;

/**
 * What `webx:doctor` says about the modules a site keeps in `modules/`: the Vue plugin their
 * components need, the `Dockerfile` that has to carry them into the front-end stage, and an id
 * that is already a packaged module's (SITE_MODULES §7, §11).
 *
 * Each case is a site of its own in a temporary folder — `vendor/composer/installed.json` with the
 * module in it and the module's folder beside — while the files the check reads from the site
 * itself go into the application's base path and are taken out again.
 */
final class SiteModulesCheckTest extends TestCase
{
    private Filesystem $files;

    private string $site;

    /** @var list<string> */
    private array $written = [];

    private const SKELETON_VITE = "import laravel from 'laravel-vite-plugin'\nimport vue from '@vitejs/plugin-vue'\n\nexport default { plugins: [laravel({ input: [] }), vue()] }\n";

    private const SKELETON_DOCKERFILE = <<<'DOCKER'
        FROM composer:2 AS vendor
        WORKDIR /app
        COPY . .
        RUN composer install --no-dev \
            && mkdir -p theme modules

        FROM node:22-alpine AS assets
        WORKDIR /app
        COPY package.json package-lock.json ./
        COPY --from=vendor /app/modules ./modules
        RUN --mount=type=cache,target=/root/.npm npm ci --no-audit --no-fund
        COPY vite.config.js ./
        RUN npm run build
        DOCKER;

    /** The Dockerfile of a site set up before site modules: the context is copied, but only into the Composer stage. */
    private const OLD_DOCKERFILE = <<<'DOCKER'
        FROM composer:2 AS vendor
        WORKDIR /app
        COPY . .
        RUN composer install --no-dev

        FROM node:22-alpine AS assets
        WORKDIR /app
        COPY package.json package-lock.json ./
        RUN --mount=type=cache,target=/root/.npm \
            npm ci --no-audit --no-fund
        COPY vite.config.js ./
        COPY resources ./resources
        RUN npm run build
        DOCKER;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->site = sys_get_temp_dir().'/webx-site-modules-'.bin2hex(random_bytes(4));
        $this->files->ensureDirectoryExists($this->site.'/vendor/composer');
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            $this->files->delete($path);
        }

        $this->files->deleteDirectory($this->site);

        parent::tearDown();
    }

    // -- the Vue plugin ------------------------------------------------------------------------

    #[Test]
    public function a_module_with_components_and_no_vue_plugin_is_red(): void
    {
        $this->module('offices', vue: true);
        $this->siteFile('vite.config.js', "import laravel from 'laravel-vite-plugin'\n\nexport default { plugins: [laravel({ input: [] })] }\n");
        $this->siteFile('package.json', (string) json_encode(['devDependencies' => ['vite' => '^8.0.0']]));
        $this->siteFile('Dockerfile', self::SKELETON_DOCKERFILE);

        $found = $this->diagnose();

        $this->assertCount(1, $found);
        $this->assertSame(Diagnosis::FAIL, $found[0]->state);
        $this->assertStringContainsString('site/module-offices has .vue files', $found[0]->detail);
        $this->assertStringContainsString('`npm install -D @vitejs/plugin-vue`', $found[0]->detail);
        $this->assertStringContainsString("in vite.config.js `import vue from '@vitejs/plugin-vue'`", $found[0]->detail);
    }

    #[Test]
    public function the_plugin_installed_and_not_loaded_asks_only_for_the_line(): void
    {
        $this->module('offices', vue: true);
        $this->siteFile('vite.config.js', "export default {}\n");
        $this->siteFile('package.json', (string) json_encode(['devDependencies' => ['@vitejs/plugin-vue' => '^6.0.1']]));

        $found = $this->diagnose();

        $this->assertSame(Diagnosis::FAIL, $found[0]->state);
        $this->assertStringNotContainsString('npm install -D', $found[0]->detail);
        $this->assertStringContainsString('`vue()` in `plugins`', $found[0]->detail);
    }

    #[Test]
    public function a_module_without_components_needs_no_plugin(): void
    {
        $this->module('offices', vue: false);
        $this->siteFile('vite.config.js', "export default {}\n");
        $this->siteFile('package.json', '{}');

        $found = $this->diagnose();

        $this->assertSame([Diagnosis::OK], array_map(static fn (Diagnosis $d): string => $d->state, $found));
    }

    #[Test]
    public function a_component_inside_an_installed_dependency_is_not_the_modules(): void
    {
        $this->module('offices', vue: false);
        $this->files->ensureDirectoryExists($this->site.'/modules/offices/js/node_modules/some-lib');
        $this->files->put($this->site.'/modules/offices/js/node_modules/some-lib/Thing.vue', '<template><i /></template>');
        $this->siteFile('vite.config.js', "export default {}\n");
        $this->siteFile('package.json', '{}');

        $this->assertSame(Diagnosis::OK, $this->diagnose()[0]->state);
    }

    // -- the Dockerfile ------------------------------------------------------------------------

    #[Test]
    public function a_dockerfile_that_installs_npm_without_the_modules_is_a_warning(): void
    {
        $this->module('offices', vue: true);
        $this->skeletonVite();
        $this->siteFile('Dockerfile', self::OLD_DOCKERFILE);

        $found = $this->diagnose();

        $this->assertCount(1, $found);
        $this->assertSame(Diagnosis::WARN, $found[0]->state);
        $this->assertTrue($found[0]->stops(true), 'a strict deploy stops: the image would not build');
        $this->assertStringContainsString('`npm ci` links @site/module-offices to a folder that is not there', $found[0]->detail);
        $this->assertStringContainsString('`COPY modules ./modules`', $found[0]->detail);
        $this->assertStringContainsString('mends its own Dockerfile', $found[0]->detail);
    }

    #[Test]
    public function the_skeletons_dockerfile_and_a_plain_copy_of_the_folder_both_carry_the_modules(): void
    {
        $this->module('offices', vue: true);
        $this->skeletonVite();

        $this->siteFile('Dockerfile', self::SKELETON_DOCKERFILE);
        $this->assertSame(Diagnosis::OK, $this->diagnose()[0]->state);

        $this->siteFile('Dockerfile', str_replace(
            "RUN --mount=type=cache,target=/root/.npm \\\n    npm ci",
            "COPY modules ./modules\nRUN --mount=type=cache,target=/root/.npm \\\n    npm ci",
            self::OLD_DOCKERFILE,
        ));
        $this->assertSame(Diagnosis::OK, $this->diagnose()[0]->state);
    }

    #[Test]
    public function the_modules_copied_after_the_install_are_too_late(): void
    {
        $this->module('offices', vue: true);
        $this->skeletonVite();
        $this->siteFile('Dockerfile', str_replace(
            'COPY resources ./resources',
            "COPY resources ./resources\nCOPY modules ./modules",
            self::OLD_DOCKERFILE,
        ));

        $this->assertSame(Diagnosis::WARN, $this->diagnose()[0]->state);
    }

    #[Test]
    public function a_module_without_an_npm_half_or_a_site_without_a_dockerfile_says_nothing_about_the_image(): void
    {
        $this->module('offices', vue: false, npm: false);
        $this->skeletonVite();
        $this->siteFile('Dockerfile', self::OLD_DOCKERFILE);
        $this->assertSame(Diagnosis::OK, $this->diagnose()[0]->state);

        $this->module('offices', vue: true);
        $this->files->delete($this->app->basePath('Dockerfile'));
        $this->assertSame(Diagnosis::OK, $this->diagnose()[0]->state);
    }

    // -- the id --------------------------------------------------------------------------------

    #[Test]
    public function a_site_module_with_the_id_of_a_catalogue_module_is_red(): void
    {
        $this->module('faq', vue: false, npm: false);
        $this->skeletonVite();

        $found = $this->diagnose();

        $this->assertCount(1, $found);
        $this->assertSame(Diagnosis::FAIL, $found[0]->state);
        $this->assertStringContainsString('site/module-faq goes by `faq`, which is the id of webx-ui/module-faq', $found[0]->detail);
        $this->assertStringContainsString('`webx-faq`', $found[0]->detail);
    }

    #[Test]
    public function a_site_module_with_the_id_of_an_installed_package_is_red(): void
    {
        $this->module('offices', vue: false, npm: false, also: [[
            'name' => 'acme/offices-directory',
            'install-path' => '../acme/offices-directory',
            'extra' => ['webx' => ['module' => 'offices']],
        ]]);
        $this->skeletonVite();

        $found = $this->diagnose();

        $this->assertSame(Diagnosis::FAIL, $found[0]->state);
        $this->assertStringContainsString('which is the id of acme/offices-directory', $found[0]->detail);
    }

    #[Test]
    public function a_site_without_modules_of_its_own_hears_nothing(): void
    {
        $this->files->put($this->site.'/vendor/composer/installed.json', (string) json_encode(['packages' => [[
            'name' => 'webx-ui/module-faq',
            'install-path' => '../webx-ui/module-faq',
            'extra' => ['webx' => ['module' => 'faq', 'npm' => ['@webx-ui/module-faq' => '^0.94.0']]],
        ]]]));
        $this->siteFile('vite.config.js', "export default {}\n");
        $this->siteFile('Dockerfile', self::OLD_DOCKERFILE);

        $this->assertSame([], $this->diagnose());
    }

    #[Test]
    public function the_doctor_runs_the_check(): void
    {
        $this->module('faq', vue: false, npm: false);

        // Every other check quiet: they look at a real site, and this one is the subject.
        /** @var list<class-string> $checks */
        $checks = (new ReflectionClassConstant(DoctorCommand::class, 'CHECKS'))->getValue();

        foreach ($checks as $check) {
            if ($check !== SiteModules::class) {
                $this->app->instance($check, new FixedCheck);
            }
        }

        $this->app->instance(SiteModules::class, new SiteModules($this->app, $this->files, $this->installedPackages(), new Catalogue($this->files)));

        $this->artisan('webx:doctor', ['--json' => true])
            ->expectsOutputToContain('site/module-faq goes by `faq`')
            ->assertFailed();
    }

    // -- helpers -------------------------------------------------------------------------------

    /** @return list<Diagnosis> */
    private function diagnose(): array
    {
        return (new SiteModules($this->app, $this->files, $this->installedPackages(), new Catalogue($this->files)))->run();
    }

    private function installedPackages(): PackageRegistry
    {
        return new PackageRegistry($this->files, $this->site.'/vendor/composer/installed.json');
    }

    /**
     * A site module in `modules/<id>`, written into the temporary site's `installed.json`.
     *
     * @param  list<array<string, mixed>>  $also  Other packages installed beside it.
     */
    private function module(string $id, bool $vue, bool $npm = true, array $also = []): void
    {
        $directory = $this->site.'/modules/'.$id;
        $this->files->deleteDirectory($directory);
        $this->files->ensureDirectoryExists($directory.'/js/src');
        $this->files->put($directory.'/js/src/index.ts', "export {}\n");

        if ($vue) {
            $this->files->put($directory.'/js/src/List.vue', "<template><div /></template>\n");
        }

        $webx = ['module' => $id];

        if ($npm) {
            $webx['npm'] = ["@site/module-{$id}" => "file:modules/{$id}/js"];
            $webx['panel'] = ['import' => "import { {$id} } from '@site/module-{$id}'", 'register' => "{$id}()"];
        }

        $this->files->put($this->site.'/vendor/composer/installed.json', (string) json_encode(['packages' => [
            ...$also,
            [
                'name' => "site/module-{$id}",
                'version' => '0.1.0',
                'install-path' => "../../modules/{$id}",
                'extra' => ['webx' => $webx],
            ],
        ]]));
    }

    private function skeletonVite(): void
    {
        $this->siteFile('vite.config.js', self::SKELETON_VITE);
        $this->siteFile('package.json', (string) json_encode(['devDependencies' => ['@vitejs/plugin-vue' => '^6.0.1', 'vite' => '^8.0.0']]));
    }

    private function siteFile(string $name, string $contents): void
    {
        $path = $this->app->basePath($name);
        $this->files->put($path, $contents);
        $this->written[] = $path;
    }
}
