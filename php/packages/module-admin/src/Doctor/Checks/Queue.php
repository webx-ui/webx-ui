<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\QueueBacklog;

/**
 * Whether what the site puts on the queue ever leaves it.
 *
 * Inbox letters, the audit and image work all go through the queue, and a queue nobody works
 * through fails silently: everything says "queued" and stays that way. A deploy is exactly when
 * a worker gets forgotten, so this is where it is asked.
 */
final class Queue implements Check
{
    public function __construct(private readonly QueueBacklog $queue) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $driver = $this->queue->driver();
        $connection = $this->queue->connection();

        if ($driver === 'sync') {
            return [Diagnosis::ok('Queue', 'sync — jobs run inside the request, there is no worker to keep running.')];
        }

        if ($driver !== 'database') {
            return [Diagnosis::ok('Queue', "{$connection} ({$driver}) — make sure a worker is running; only the database queue can be looked into from here.")];
        }

        try {
            if (! $this->queue->hasTable()) {
                return [Diagnosis::fail('Queue', "the {$this->queue->table()} table of the database queue is not there — run `php artisan make:queue-table && php artisan migrate`.")];
            }
        } catch (Throwable $failure) {
            return [Diagnosis::warn('Queue', 'the queue table could not be read: '.$failure->getMessage())];
        }

        $stalled = $this->queue->stalled();

        if ($stalled > 0) {
            return [Diagnosis::warn(
                'Queue',
                ($stalled === 1 ? '1 job has' : "{$stalled} jobs have").' waited more than '.QueueBacklog::MINUTES
                .' minutes and no worker took '.($stalled === 1 ? 'it' : 'them').' — letters, audits and image work stay queued. '
                .ucfirst(QueueBacklog::advice()).'.',
            )];
        }

        return [Diagnosis::ok('Queue', 'database — nothing has waited longer than '.QueueBacklog::MINUTES.' minutes.')];
    }
}
