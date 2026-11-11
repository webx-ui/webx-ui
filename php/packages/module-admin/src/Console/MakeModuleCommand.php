<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Panel\PackageRegistry;
use WebxUi\Admin\Setup\Catalogue;
use WebxUi\Admin\Setup\Processes;
use WebxUi\Admin\SiteModules\ModuleName;
use WebxUi\Admin\SiteModules\Scaffold;
use WebxUi\Admin\SiteModules\SiteWiring;

/**
 * A module of the panel, two ways.
 *
 * Without `--local`, the class of a section in `app/Cms/Modules` and its two tests — what a site
 * needed before it could keep modules of its own, kept for the sites that use it.
 *
 * With `--local`, a site module (SITE_MODULES §9): `modules/<name>` with both halves, built like a
 * packaged module so that it can move into the monorepo as a folder; the site's files wired to it;
 * then the install, as a child `webx:module:add` — this process booted before the package
 * existed, the reason `webx:setup` gives — then `migrate`, and the module's block types if it has
 * any. The scaffold refuses an id a packaged module has or the site already uses: two owners of
 * one set of tables, permissions and `webx-<id>` is a site that does not boot.
 */
final class MakeModuleCommand extends Command
{
    protected $signature = 'webx:make-module {name : Name of the module, e.g. Pages, or offices with --local}
                            {--local : A module of the site in modules/<name>, both halves, installed as a path package}
                            {--no-install : With --local, write the module and wire the site, and leave Composer, npm and the migrations}
                            {--force : Overwrite the files of the scaffold if they already exist, and nothing else}';

    protected $description = 'Create an admin panel module: a class, or with --local a module of the site in modules/';

    public function handle(Filesystem $files): int
    {
        if ($this->option('local')) {
            return $this->local($files);
        }

        $name = Str::studly((string) $this->argument('name'));
        $class = Str::endsWith($name, 'Module') ? $name : $name.'Module';
        $id = Str::kebab(Str::beforeLast($class, 'Module'));

        $namespace = $this->laravel->getNamespace().'Cms\\Modules';
        $path = $this->laravel->path('Cms/Modules/'.$class.'.php');

        if ($files->exists($path) && ! $this->option('force')) {
            $this->components->error("{$class} already exists. Pass --force to overwrite it.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $this->render($files, 'module.stub', $namespace, $class, $id));

        // The budget comes with the module for the same reason: a module that never measured its
        // pages is the ordinary way to have an N+1 nobody sees until a site has a thousand rows.
        $test = $this->laravel->basePath('tests/Feature/Cms/'.$class.'PerformanceTest.php');

        if (! $files->exists($test) || $this->option('force')) {
            $files->ensureDirectoryExists(dirname($test));
            $files->put($test, $this->render($files, 'module-performance-test.stub', 'Tests\\Feature\\Cms', $class, $id));
        }

        // And the test that keeps it closed: a route without cms.can is open to every administrator,
        // and nothing else notices until somebody without a role reads what they should not.
        $access = $this->laravel->basePath('tests/Feature/Cms/'.$class.'AccessTest.php');

        if (! $files->exists($access) || $this->option('force')) {
            $files->ensureDirectoryExists(dirname($access));
            $files->put($access, $this->render($files, 'module-access-test.stub', 'Tests\\Feature\\Cms', $class, $id));
        }

        // The demo folder comes with the module rather than being remembered later: a section
        // with nothing to show on a fresh site is the ordinary way to have no demo at all.
        $demo = $this->laravel->resourcePath('demo/'.$id);
        $files->ensureDirectoryExists($demo);
        $files->put($demo.'/.gitkeep', '');

        $this->components->info("Module [{$class}] created.");
        $this->components->bulletList([
            "Register it: app(WebxUi\\Admin\\ModuleRegistry::class)->register(new \\{$namespace}\\{$class}());",
            'Do that from a service provider, so the panel knows about it on every request.',
            "Demo fixtures go in resources/demo/{$id}; seed() fills them in and webx:demo runs it.",
            "Give its pages and lists their query budgets in tests/Feature/Cms/{$class}PerformanceTest.php.",
            "Put every route behind cms.can and every tool behind a permission; tests/Feature/Cms/{$class}AccessTest.php holds them.",
            'A module with a front end, migrations, MCP and help of its own: webx:make-module --local <name>.',
        ]);

        return self::SUCCESS;
    }

    private function local(Filesystem $files): int
    {
        try {
            $name = ModuleName::from((string) $this->argument('name'));
        } catch (InvalidArgumentException $invalid) {
            $this->components->error($invalid->getMessage());

            return self::FAILURE;
        }

        $refusal = $this->refusal($files, $name);

        if ($refusal !== null) {
            $this->components->error($refusal);

            return self::FAILURE;
        }

        /** @var list<string> $languages */
        $languages = array_values(array_filter((array) config('webx-localization.panel', []), is_string(...)));
        $folder = $this->laravel->basePath($name->folder());

        $this->components->info("{$name->folder()} — {$name->package()}, {$name->npm()}");

        foreach ($this->laravel->make(Scaffold::class)->write($name, $folder, $languages) as $path => $status) {
            $this->components->twoColumnDetail($path, $status);
        }

        $this->newLine();
        $this->components->info('The site');

        foreach ($this->laravel->make(SiteWiring::class)->wire($this->laravel->basePath(), $name) as $file => $what) {
            $this->components->twoColumnDetail($file, $what);
        }

        if ($this->option('no-install')) {
            $this->newLine();
            $this->components->twoColumnDetail('Then', "php artisan webx:module:add {$name->package()} && php artisan migrate");

            return self::SUCCESS;
        }

        return $this->install($name);
    }

    /**
     * Why this id cannot be the site's own module, or null when it can.
     *
     * A run with `--force` over the module this command made is the one case of the id being
     * taken that is no conflict: the owner is the module itself.
     */
    private function refusal(Filesystem $files, ModuleName $name): ?string
    {
        $catalogue = $this->laravel->make(Catalogue::class);

        if ($catalogue->knows($name->id)) {
            return "[{$name->id}] is the id of {$catalogue->packageFor($name->id)}, a module of WebX UI: `php artisan webx:module:add {$name->id}` installs it. A module of the site needs a name of its own.";
        }

        foreach ($this->laravel->make(PackageRegistry::class)->packages() as $package) {
            if ($package->module === $name->id && $package->name !== $name->package()) {
                return "[{$name->id}] is the id of {$package->name}, installed on this site. A module of the site needs a name of its own.";
            }
        }

        $modules = $this->laravel->make(ModuleRegistry::class);

        if ($modules->has($name->id) && ! str_starts_with($modules->get($name->id)::class, $name->namespace())) {
            return "[{$name->id}] is the id of ".$modules->get($name->id)::class.', a section this site already has. A module of the site needs a name of its own.';
        }

        if ($files->exists($this->laravel->basePath($name->folder())) && ! $this->option('force')) {
            return "{$name->folder()} is already there. Pass --force to write the files of the scaffold over it again; nothing else in the folder is touched.";
        }

        return null;
    }

    /**
     * The install of a package, as for any other: `webx:module:add` does Composer, the panel's
     * entry file, the ready-made roles, npm and the build. Then what it leaves to the deploy, run
     * here because the person making a module is the one about to open it.
     */
    private function install(ModuleName $name): int
    {
        $processes = $this->laravel->make(Processes::class);
        $artisan = $processes->artisan($this->laravel->basePath());
        $steps = [
            ['webx:module:add', $name->package()],
            ['migrate', '--force'],
        ];

        if ((glob($this->laravel->basePath($name->folder().'/resources/blocks/*.json')) ?: []) !== []) {
            $steps[] = ['webx:blocks:offered', '--install', '--module='.$name->id];
        }

        foreach ($steps as $arguments) {
            $this->newLine();
            $this->components->info('php artisan '.implode(' ', $arguments));

            $status = $processes->run(
                [...$artisan, ...$arguments],
                $this->laravel->basePath(),
                output: fn (string $buffer) => $this->output->write($buffer),
            );

            if ($status !== 0) {
                $this->components->error('php artisan '.implode(' ', $arguments)." failed (exit {$status}). The module and the wiring are written: run it again when the cause is fixed.");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->components->info("{$name->package()} is installed.");
        $this->components->bulletList([
            "The section is in the panel; its demo: php artisan webx:demo --module={$name->id}.",
            'Its tests: php artisan test --testsuite=Modules, npm run typecheck:modules, npm run test:modules.',
            'What it owns and how to change it: '.$name->folder().'/AGENTS.md.',
            'php artisan webx:doctor checks the Vue plugin, the Dockerfile and the id.',
        ]);

        return self::SUCCESS;
    }

    private function render(Filesystem $files, string $stub, string $namespace, string $class, string $id): string
    {
        return str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ id }}', '{{ title }}'],
            [rtrim($namespace, '\\'), $class, $id, Str::headline($id)],
            $files->get(__DIR__.'/../../stubs/'.$stub),
        );
    }
}
