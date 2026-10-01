<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use WebxUi\Admin\Setup\Catalogue;
use WebxUi\Admin\Setup\Processes;
use WebxUi\Admin\Setup\SetupFailed;

/**
 * Install one module into a site that already has a panel: Composer, then the panel's front end.
 *
 * What a person does by hand after `webx:setup`, in the order it has to happen, for a script to
 * run without reading the output: `composer require`, `webx:panel --sync`, `npm install` and,
 * unless told otherwise, `npm run build`. Any package will do, the site's own and a third
 * party's included — the catalogue only lends its ids (`faq` for `webx-ui/module-faq`) and the
 * shared version range of the `webx-ui/*` packages.
 *
 * The sync runs as its own `artisan` for the reason `webx:setup` gives: this process booted
 * before the package was installed, and its autoloader and its list of installed packages are
 * the old ones. The migrations are not run: the module's tables are the deploy's business, the
 * same `migrate --force` that every release of the site runs.
 *
 * Running it again is not an error. An installed package skips Composer and still gets the
 * rest, which is also how a run that stopped at npm is finished.
 */
final class ModuleAddCommand extends Command
{
    protected $signature = 'webx:module:add
                            {package : The Composer package, vendor/package[:constraint], or a module id from webx:modules}
                            {--no-build : Run npm install but not the build}
                            {--composer= : The Composer binary, when it is not on the PATH}';

    protected $description = 'Install a module: composer require, webx:panel --sync, npm install and build';

    private Processes $processes;

    private string $given = '';

    public function handle(Catalogue $catalogue, Processes $processes): int
    {
        $this->processes = $processes;
        $this->given = trim((string) $this->argument('package'));
        $package = $this->given;

        try {
            [$package, $constraint] = $this->resolve($catalogue, $this->given);

            if ($catalogue->has($package)) {
                $this->components->twoColumnDetail($package, 'already installed');
            } else {
                $this->install($catalogue, $package, $constraint);
            }

            $this->artisan(['webx:panel', '--sync'], 'webx:panel --sync');

            $npm = $processes->npm();
            $this->step([...$npm, 'install'], 'npm install');

            if ($this->option('no-build')) {
                $this->components->twoColumnDetail('Build', 'skipped — npm run build when it is time');
            } else {
                $this->step([...$npm, 'run', 'build'], 'npm run build');
            }
        } catch (SetupFailed $failure) {
            $this->newLine();
            $this->components->error($failure->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info("{$package} is installed.");
        $this->components->twoColumnDetail('Then', 'php artisan migrate — its tables are not created yet');

        return self::SUCCESS;
    }

    /**
     * The package and the range to ask for, from whatever the person typed.
     *
     * @return array{string, string|null}
     */
    private function resolve(Catalogue $catalogue, string $given): array
    {
        if ($catalogue->knows($given)) {
            return [$catalogue->packageFor($given), null];
        }

        [$package, $constraint] = array_pad(explode(':', $given, 2), 2, null);
        $package = strtolower((string) $package);

        // Composer's own pattern for a package name. Anything else is refused here, before a
        // `composer require` that would read it as an option or a path.
        if (preg_match('{^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$}', $package) !== 1) {
            throw SetupFailed::unknownPackage($given, implode(', ', $catalogue->ids()));
        }

        return [$package, $constraint === '' ? null : $constraint];
    }

    private function install(Catalogue $catalogue, string $package, ?string $constraint): void
    {
        // The packages of this repository ship on one version, so one of them is asked for at
        // the range of the frame already installed — the same rule `webx:setup` follows.
        $constraint ??= str_starts_with($package, 'webx-ui/') ? $catalogue->constraint() : null;
        $named = $this->option('composer');

        $this->components->info("composer require {$package}");

        $this->step(
            [
                ...$this->processes->composer($this->laravel->basePath(), is_string($named) ? $named : null),
                'require',
                $constraint === null ? $package : $package.':'.$constraint,
                '--no-interaction',
                '--no-progress',
            ],
            'composer require',
        );

        $catalogue->refresh();

        if (! $catalogue->has($package)) {
            throw SetupFailed::notInstalled($package);
        }
    }

    /** @param  list<string>  $arguments */
    private function artisan(array $arguments, string $what): void
    {
        $this->step([...$this->processes->artisan($this->laravel->basePath()), ...$arguments], $what);
    }

    /**
     * Every step is fatal: a script that goes on past a failed `npm install` deploys a panel
     * that does not build, and says it succeeded.
     *
     * @param  list<string>  $command
     */
    private function step(array $command, string $what): void
    {
        $status = $this->processes->run(
            $command,
            $this->laravel->basePath(),
            output: fn (string $buffer) => $this->output->write($buffer),
        );

        if ($status !== 0) {
            throw SetupFailed::step($what, $status, 'php artisan webx:module:add '.$this->given);
        }
    }
}
