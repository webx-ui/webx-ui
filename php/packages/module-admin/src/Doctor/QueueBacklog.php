<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Throwable;

/**
 * Whether the database queue is moving.
 *
 * `QUEUE_CONNECTION=database` with no worker looks exactly like a working site: forms are
 * accepted, an audit says it has started, letters say they are queued — and nothing ever leaves
 * the `jobs` table. The one sign is a job that was due minutes ago and nobody has picked up, so
 * that is what is counted. A delayed job is not due yet and a reserved one is being worked on;
 * neither is a sign of anything.
 *
 * Asked by `webx:doctor` and by the audit's `config.queue`, so the two say the same thing.
 * Only the database driver can be read this cheaply; for any other the answer is null.
 */
final readonly class QueueBacklog
{
    /** Longer than any worker sleeps between polls, shorter than anybody waits for a letter. */
    public const MINUTES = 5;

    public function __construct(
        private Repository $config,
        private DatabaseManager $db,
    ) {}

    /** The queue connection the site sends jobs to, by name. */
    public function connection(): string
    {
        return (string) $this->config->get('queue.default', 'sync');
    }

    public function driver(): string
    {
        $connection = $this->connection();

        return (string) $this->config->get("queue.connections.{$connection}.driver", $connection);
    }

    /** The table of the database queue, or null for any other driver. */
    public function table(): ?string
    {
        if ($this->driver() !== 'database') {
            return null;
        }

        return (string) $this->config->get('queue.connections.'.$this->connection().'.table', 'jobs');
    }

    /** False when the database queue has no table to put jobs in; true for any other driver. */
    public function hasTable(): bool
    {
        $table = $this->table();

        return $table === null || $this->database()->getSchemaBuilder()->hasTable($table);
    }

    /**
     * Jobs that have been due for longer than {@see MINUTES} and are not being worked on — zero
     * for a queue that is not the database one, or has no table yet.
     */
    public function stalled(int $minutes = self::MINUTES): int
    {
        $table = $this->table();

        if ($table === null) {
            return 0;
        }

        try {
            return $this->database()->table($table)
                ->whereNull('reserved_at')
                ->where('available_at', '<=', time() - $minutes * 60)
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /** The same sentence for both places that say it. */
    public static function advice(): string
    {
        return 'keep a worker running (`php artisan queue:work` under a supervisor), or schedule '
            .'`queue:work --stop-when-empty` every minute, or set QUEUE_CONNECTION=sync on a small site';
    }

    private function database(): Connection
    {
        $connection = $this->config->get('queue.connections.'.$this->connection().'.connection');

        return $this->db->connection(is_string($connection) && $connection !== '' ? $connection : null);
    }
}
