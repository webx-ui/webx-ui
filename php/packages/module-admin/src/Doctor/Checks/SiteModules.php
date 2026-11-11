<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Panel\PackageRegistry;
use WebxUi\Admin\Panel\PanelPackage;
use WebxUi\Admin\Panel\Vendors;
use WebxUi\Admin\Setup\Catalogue;

/**
 * The modules a site keeps in its own `modules/` folder, and the three files of the site they
 * lean on (SITE_MODULES §7, §11).
 *
 * A packaged module arrives built; a site module is built by the site's Vite from its sources,
 * so its single-file components need `@vitejs/plugin-vue` there — the skeleton has it since site
 * modules exist, a site set up earlier does not. The image is the same story: its front-end stage
 * installs `file:modules/<name>/js`, and a `Dockerfile` written before that copies nothing of
 * `modules/` before `npm ci`: npm links the package to a folder that is not there without a word,
 * and the build stops on the import. Both files are the site's, a release does not change
 * them, so this is where an older site finds out. The third is the id: a site module named like
 * a module of the catalogue is two owners of one set of tables, permissions and `webx-<id>`, and
 * the day the packaged one is installed they collide.
 *
 * Silent on a site without modules of its own — there is nothing here for it to fix.
 */
final class SiteModules implements Check
{
    private const SUBJECT = 'Site modules';

    /** The names Vite looks for, in its own order. */
    private const VITE_CONFIGS = ['vite.config.js', 'vite.config.mjs', 'vite.config.ts', 'vite.config.cjs', 'vite.config.mts', 'vite.config.cts'];

    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
        private readonly PackageRegistry $registry,
        private readonly Catalogue $catalogue,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $modules = array_values(array_filter(
            $this->registry->packages(),
            static fn (PanelPackage $package): bool => Vendors::local($package->name),
        ));

        if ($modules === []) {
            return [];
        }

        $found = [...$this->vue($modules), ...$this->image($modules), ...$this->ids($modules)];

        return $found !== [] ? $found : [Diagnosis::ok(
            self::SUBJECT,
            count($modules).' ('.implode(', ', array_map(static fn (PanelPackage $p): string => $p->name, $modules)).') — built by the site\'s Vite, copied into the image, ids of their own.',
        )];
    }

    /**
     * @param  list<PanelPackage>  $modules
     * @return list<Diagnosis>
     */
    private function vue(array $modules): array
    {
        $components = array_values(array_filter($modules, $this->hasComponents(...)));

        if ($components === []) {
            return [];
        }

        $config = null;

        foreach (self::VITE_CONFIGS as $name) {
            if ($this->files->exists($this->app->basePath($name))) {
                $config = $name;

                break;
            }
        }

        $loaded = $config !== null && str_contains((string) $this->files->get($this->app->basePath($config)), '@vitejs/plugin-vue');
        $installed = $this->manifestHas('@vitejs/plugin-vue');

        if ($loaded && $installed) {
            return [];
        }

        $names = implode(', ', array_map(static fn (PanelPackage $p): string => $p->name, $components));
        $config ??= 'vite.config.js';

        $steps = [
            ...($installed ? [] : ['`npm install -D @vitejs/plugin-vue`']),
            ...($loaded ? [] : ["in {$config} `import vue from '@vitejs/plugin-vue'` and `vue()` in `plugins`, beside `laravel()`"]),
        ];

        return [Diagnosis::fail(
            self::SUBJECT,
            "{$names} ".(count($components) === 1 ? 'has' : 'have').' .vue files, and the site\'s Vite has no Vue plugin to build them, so `npm run build` stops — '
            .implode(', then ', $steps).' (the skeleton\'s vite.config.js shows the line).',
        )];
    }

    /**
     * @param  list<PanelPackage>  $modules
     * @return list<Diagnosis>
     */
    private function image(array $modules): array
    {
        $npm = array_values(array_filter($modules, static fn (PanelPackage $p): bool => $p->npm !== []));
        $path = $this->app->basePath('Dockerfile');

        if ($npm === [] || ! $this->files->exists($path) || $this->copiesModules((string) $this->files->get($path))) {
            return [];
        }

        return [Diagnosis::warn(
            self::SUBJECT,
            'the Dockerfile installs the npm packages without modules/ in the build: `npm ci` links '
            .implode(', ', array_map(static fn (PanelPackage $p): string => (string) array_key_first($p->npm), $npm))
            .' to a folder that is not there, and `npm run build` stops on the import — before `npm ci` in the front-end stage add `COPY modules ./modules`'
            .' (the skeleton copies it from the Composer stage, which always has the folder). A site set up before site modules mends its own Dockerfile: a release does not.',
        )];
    }

    /**
     * @param  list<PanelPackage>  $modules
     * @return list<Diagnosis>
     */
    private function ids(array $modules): array
    {
        $packaged = [];

        foreach ($this->registry->packages() as $package) {
            if ($package->module !== null && ! Vendors::local($package->name)) {
                $packaged[$package->module] = $package->name;
            }
        }

        $found = [];

        foreach ($modules as $module) {
            $id = $module->module;

            if ($id === null) {
                continue;
            }

            $owner = $packaged[$id] ?? ($this->catalogue->knows($id) ? $this->catalogue->packageFor($id) : null);

            if ($owner !== null) {
                $found[] = Diagnosis::fail(
                    self::SUBJECT,
                    "{$module->name} goes by `{$id}`, which is the id of {$owner} — two modules would share its tables, permissions and `webx-{$id}`. Rename the site module (its id in `extra.webx.module`, the config, the translations, the tables and the permissions) while it is the only one.",
                );
            }
        }

        return $found;
    }

    /** Whether a module carries a single-file component anywhere but in an installed dependency. */
    private function hasComponents(PanelPackage $module): bool
    {
        $directory = $this->registry->directory($module->name);

        if ($directory === null || ! is_dir($directory)) {
            return false;
        }

        $finder = Finder::create()->files()->in($directory)->name('*.vue')->exclude(['node_modules', 'vendor'])->ignoreDotFiles(true);

        return $finder->hasResults();
    }

    private function manifestHas(string $dependency): bool
    {
        $path = $this->app->basePath('package.json');

        if (! $this->files->exists($path)) {
            return false;
        }

        $manifest = json_decode((string) $this->files->get($path), true);

        foreach (['devDependencies', 'dependencies'] as $section) {
            if (is_array($manifest) && is_array($manifest[$section] ?? null) && array_key_exists($dependency, $manifest[$section])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether every stage that installs npm packages has the site's modules in it by then.
     *
     * Read by instruction, not by text: `COPY . .` in the Composer stage carries `modules/` too,
     * and says nothing about the stage that runs `npm ci`. What counts is a COPY or ADD earlier in
     * that same stage whose source is the folder, a module inside it, the whole context, or
     * another stage's `…/modules`.
     */
    private function copiesModules(string $dockerfile): bool
    {
        $instructions = preg_split('/\R/', (string) preg_replace('/\\\\\R/', ' ', $dockerfile)) ?: [];

        $copied = false;

        foreach ($instructions as $line) {
            $line = trim($line);

            if (preg_match('/^FROM\s/i', $line) === 1) {
                $copied = false;

                continue;
            }

            if (preg_match('/^(?:COPY|ADD)\s+(.+)$/i', $line, $matches) === 1) {
                $arguments = array_values(array_filter(
                    preg_split('/\s+/', trim($matches[1])) ?: [],
                    static fn (string $argument): bool => ! str_starts_with($argument, '--'),
                ));

                foreach (array_slice($arguments, 0, -1) as $source) {
                    if (preg_match('#^(\./?|(\./)?modules(/.*)?|.*/modules/?)$#', $source) === 1) {
                        $copied = true;
                    }
                }

                continue;
            }

            if (preg_match('/^RUN\s.*\bnpm\s+(?:ci|install|i)\b/i', $line) === 1) {
                if (! $copied) {
                    return false;
                }
            }
        }

        // A Dockerfile that installs nothing from npm builds no front end, and has nothing to copy.
        return true;
    }
}
