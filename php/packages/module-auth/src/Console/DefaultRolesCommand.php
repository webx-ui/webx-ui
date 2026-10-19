<?php

declare(strict_types=1);

namespace WebxUi\Auth\Console;

use Illuminate\Console\Command;
use WebxUi\Auth\Access\DefaultRoles;

/**
 * The ready-made roles, again: for a module installed after them. The roles there are get the
 * permissions of that module their definition names, unless they were offered them before and
 * the owner took them away; the roles that are missing are created, unless `--top-up` says
 * not to — which is how `webx:boot` and `webx:module:add` run it, so that a role the owner
 * deleted is not brought back by the next deploy. Running it twice is running it once.
 */
final class DefaultRolesCommand extends Command
{
    protected $signature = 'webx:roles:defaults
                            {--top-up : Only add the permissions of new modules to the ready-made roles there are; create none}
                            {--dry-run : Print what would change and change nothing}';

    protected $description = 'Create the ready-made roles of the panel that are missing and top up the rest with the permissions of new modules';

    public function handle(DefaultRoles $roles): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $done = $roles->sync($dryRun, create: ! $this->option('top-up'));

        if ($done['created'] === [] && $done['added'] === []) {
            $this->components->info('Every ready-made role is already there and has every permission it is owed.');

            return self::SUCCESS;
        }

        foreach ($done['created'] as $slug => $permissions) {
            $this->components->twoColumnDetail("{$slug} <fg=gray>(new)</>", implode(', ', $permissions));
        }

        foreach ($done['added'] as $slug => $permissions) {
            $this->components->twoColumnDetail("{$slug} <fg=gray>+</>", implode(', ', $permissions));
        }

        $this->newLine();

        $dryRun
            ? $this->components->warn('Dry run: nothing was changed.')
            : $this->components->info('The ready-made roles are up to date with the modules installed.');

        return self::SUCCESS;
    }
}
