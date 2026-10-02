<?php

declare(strict_types=1);

namespace WebxUi\Routing\Console;

use Illuminate\Console\Command;
use WebxUi\Routing\RegistryHealth;

/**
 * Is the registry still telling the truth? {@see RegistryHealth} looks; this prints.
 *
 * Exit code 1 when anything is found, so a deploy can run it and stop.
 */
final class CheckRoutesCommand extends Command
{
    protected $signature = 'webx:routes:check';

    protected $description = 'Report addresses that no longer match the entities or the application';

    public function handle(RegistryHealth $health): int
    {
        $problems = $health->problems();

        if ($problems === []) {
            $this->components->info('Every address has an entity, every entity has an address, and the application answers none of them itself.');

            return self::SUCCESS;
        }

        $this->table(['Problem', 'Where'], array_map(static fn (array $problem): array => [$problem[1], $problem[2]], $problems));
        $this->components->error(count($problems).' problem(s) found.');

        return self::FAILURE;
    }
}
