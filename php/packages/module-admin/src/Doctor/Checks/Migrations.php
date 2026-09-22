<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Migrations\Migrator;
use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;

/**
 * Whether the database is as new as the code that talks to it.
 *
 * Every package registers its own migration path, and Laravel sorts them all into one list by
 * file name, so a module installed today brings migrations that sort in among the ones that ran
 * last year. A deploy that forgets `migrate` therefore fails on a column rather than on a
 * table, far from anything that names the cause.
 *
 * The same question answers the other trap in the same place. A migration that MariaDB refuses
 * — a foreign key into a table that sorts later, a default longer than its column (CLAUDE.md
 * §4) — is never written into the repository, so it shows up here as pending, whatever the
 * deploy log said. On sqlite neither refusal happens at all, which is worth saying out loud on
 * a site that is running on one.
 */
final class Migrations implements Check
{
    public function __construct(
        private readonly Application $app,
        private readonly Migrator $migrator,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        try {
            $driver = $this->migrator->resolveConnection(null)->getDriverName();
        } catch (Throwable $failure) {
            return [Diagnosis::fail(
                'Migrations',
                'the database cannot be reached: '.$failure->getMessage(),
            )];
        }

        if (! $this->migrator->repositoryExists()) {
            return [Diagnosis::fail(
                'Migrations',
                "there is no migrations table on [{$driver}] — run `php artisan migrate`.",
            )];
        }

        $ran = $this->migrator->getRepository()->getRan();

        // The application's own directory is not among the paths the migrator collects: every
        // package registers itself there, and `database/migrations` is added by the command.
        $paths = [...$this->migrator->paths(), $this->app->databasePath('migrations')];

        $files = array_keys($this->migrator->getMigrationFiles($paths));
        $pending = array_values(array_diff($files, $ran));

        $found = [];

        if ($pending !== []) {
            $found[] = Diagnosis::fail(
                'Migrations',
                count($pending).' have not run — '.implode(', ', array_slice($pending, 0, 3))
                .(count($pending) > 3 ? ', …' : '').' — run `php artisan migrate`.',
            );
        } else {
            $found[] = Diagnosis::ok('Migrations', count($ran)." applied on [{$driver}], none pending.");
        }

        if ($driver === 'sqlite') {
            $found[] = Diagnosis::warn(
                'Database',
                'sqlite keeps quiet about three things a real server refuses — foreign keys, defaults longer than their column, and migrations of one package ordered against another. Develop on the engine the site will run on.',
            );
        }

        return $found;
    }
}
