<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use WebxUi\Admin\Cache\CacheReset;

/**
 * «Reset the site cache» from the console (WEBX_UI_CACHE.md §5.2): the same move of versions the
 * panel's button makes, and the same row in the journal — signed by nobody, from the console.
 * Not `cache:clear`: that one takes the sessions, the locks and the rate limits with it.
 */
final class CacheResetCommand extends Command
{
    protected $signature = 'webx:cache:reset
                            {--group=* : Only these groups (`--group=menu --group=seo`); every group without it}';

    protected $description = 'Reset the site cache by moving the versions of its groups — all of them or the ones named';

    public function handle(CacheReset $reset): int
    {
        /** @var list<string> $asked */
        $asked = array_values(array_filter(array_map(
            static fn (mixed $group): string => trim((string) $group),
            (array) $this->option('group'),
        ), static fn (string $group): bool => $group !== ''));

        try {
            $before = $this->versions($reset);
            $moved = $reset->reset($asked);
            $after = $this->versions($reset);
        } catch (InvalidArgumentException $failure) {
            $this->components->error($failure->getMessage());

            return self::FAILURE;
        }

        if ($moved === []) {
            $this->components->warn('No cache group is declared: nothing to reset.');

            return self::SUCCESS;
        }

        foreach ($moved as $group) {
            $this->components->twoColumnDetail($group, ($before[$group] ?? '?').' → '.($after[$group] ?? '?'));
        }

        $this->components->info(sprintf('Reset %d cache group%s on the %s store.', count($moved), count($moved) === 1 ? '' : 's', $reset->store()));

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function versions(CacheReset $reset): array
    {
        $versions = [];

        foreach ($reset->report()['groups'] as $row) {
            $versions[$row['id']] = $row['version'];
        }

        return $versions;
    }
}
