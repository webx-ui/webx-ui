<?php

declare(strict_types=1);

namespace WebxUi\Auth\Console;

use Illuminate\Console\Command;
use WebxUi\Auth\Access\DefaultRoles;

/**
 * The ready-made roles, again: for a module installed after the first administrator, whose
 * permissions a role that did not exist then can now be made of. Roles that exist are left
 * alone, so running it twice is running it once.
 */
final class DefaultRolesCommand extends Command
{
    protected $signature = 'webx:roles:defaults';

    protected $description = 'Create the ready-made roles of the panel that are missing';

    public function handle(DefaultRoles $roles): int
    {
        $created = $roles->seed();

        if ($created === []) {
            $this->components->info('Every ready-made role is already there.');

            return self::SUCCESS;
        }

        $this->components->info('Roles created: '.implode(', ', $created).'.');

        return self::SUCCESS;
    }
}
