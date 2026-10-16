<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\QueryException;

/**
 * Drop the expired rows of the `database` cache store (WEBX_UI_CACHE.md §4).
 *
 * Laravel deletes an expired row when somebody asks for it, and only then: an entry under a
 * version nobody reads any more — the point of versioned keys — stays in the table until
 * something sweeps it. This is the sweep, hourly on the schedule. On any other store it does
 * nothing and says so.
 */
final class CachePruneCommand extends Command
{
    protected $signature = 'webx:cache:prune';

    protected $description = 'Delete the expired rows of the database cache store';

    public function handle(Config $config, ConnectionResolverInterface $db): int
    {
        $store = $config->get('cache.default');
        $store = is_string($store) ? $store : 'array';
        $driver = $config->get("cache.stores.{$store}.driver");

        if ($driver !== 'database') {
            $this->components->info(sprintf('The %s store (%s) prunes itself; nothing to do.', $store, is_string($driver) ? $driver : '?'));

            return self::SUCCESS;
        }

        $connection = $config->get("cache.stores.{$store}.connection");
        $connection = $db->connection(is_string($connection) ? $connection : null);
        $removed = 0;

        // Laravel's own config leaves `lock_table` null: the locks then live in `cache_locks`.
        foreach ([$config->get("cache.stores.{$store}.table") ?? 'cache', $config->get("cache.stores.{$store}.lock_table") ?? 'cache_locks'] as $table) {
            if (! is_string($table) || $table === '') {
                continue;
            }

            try {
                // The store keeps `expiration` as a Unix timestamp, whatever the database.
                $gone = $connection->table($table)->where('expiration', '<=', time())->delete();
            } catch (QueryException $failure) {
                $this->components->warn("The {$table} table could not be pruned: ".$failure->getMessage());

                continue;
            }

            $this->components->twoColumnDetail($table, $gone === 0 ? 'nothing expired' : "{$gone} removed");
            $removed += $gone;
        }

        $this->components->info(sprintf('Pruned %d expired row%s.', $removed, $removed === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
