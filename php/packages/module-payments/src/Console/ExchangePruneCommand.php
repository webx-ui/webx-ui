<?php

declare(strict_types=1);

namespace WebxUi\Payments\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use WebxUi\Payments\Exchange\ExchangeConnection;
use WebxUi\Payments\Exchange\ExchangeJournal;

/**
 * The one way events leave the exchange journal (spec §20.6): asked for by a person, never on a
 * schedule, and never younger than a year (decision 51) — a dispute with a gateway or a bank comes
 * months after the payment.
 */
final class ExchangePruneCommand extends Command
{
    /** Events younger than this are never removed, whatever `--days` says. */
    public const int MIN_DAYS = 365;

    private const int BATCH = 1000;

    protected $signature = 'webx:payments:exchange-prune
        {--days= : Remove events older than this many days; at least 365}
        {--dry-run : Only count what would be removed}
        {--force : Do not ask for confirmation}';

    protected $description = 'Remove events of the payment gateway exchange journal older than N days (at least 365).';

    public function handle(ConnectionResolverInterface $db, ExchangeConnection $connection, LoggerInterface $log): int
    {
        $days = $this->option('days');

        if (! is_string($days) || preg_match('/^\d+$/', $days) !== 1) {
            $this->components->error('Say how old the events to remove are: --days=N, at least '.self::MIN_DAYS.'. Nothing was removed.');

            return SymfonyCommand::INVALID;
        }

        if ((int) $days < self::MIN_DAYS) {
            $this->components->error('Exchange events younger than '.self::MIN_DAYS." days are never pruned: --days={$days} is refused. Nothing was removed.");

            return SymfonyCommand::INVALID;
        }

        $before = CarbonImmutable::now()->subDays((int) $days);
        $query = fn (): Builder => $db->connection($connection->name())->table(ExchangeJournal::TABLE)->where('created_at', '<', $before->format('Y-m-d H:i:s'));

        $count = $query()->count();
        $bytes = (int) $query()->sum($db->connection($connection->name())->raw('COALESCE(LENGTH(request_body), 0) + COALESCE(LENGTH(response_body), 0)'));
        $what = sprintf('%d %s older than %s (%s of bodies)', $count, $count === 1 ? 'event' : 'events', $before->toDateString(), self::size($bytes));

        if ($this->option('dry-run')) {
            $this->components->info("Would remove {$what}.");

            return SymfonyCommand::SUCCESS;
        }

        if ($count === 0) {
            $this->components->info("Nothing to remove: no events older than {$before->toDateString()}.");

            return SymfonyCommand::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Remove {$what}? This cannot be undone.")) {
            $this->components->warn('Nothing was removed.');

            return SymfonyCommand::FAILURE;
        }

        $removed = 0;

        // By id in batches: one long delete would hold the table for the gateways' notifications.
        do {
            $ids = $query()->orderBy('id')->limit(self::BATCH)->pluck('id')->all();

            if ($ids !== []) {
                $removed += $db->connection($connection->name())->table(ExchangeJournal::TABLE)->whereIn('id', $ids)->delete();
            }
        } while (count($ids) === self::BATCH);

        $log->notice('webx-payments: exchange journal pruned', [
            'removed' => $removed,
            'older_than' => $before->toIso8601String(),
            'days' => (int) $days,
        ]);

        $this->components->info(sprintf('Removed %d %s older than %s.', $removed, $removed === 1 ? 'event' : 'events', $before->toDateString()));

        return SymfonyCommand::SUCCESS;
    }

    private static function size(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 * 1024 * 1024 => sprintf('%.1f GB', $bytes / 1024 / 1024 / 1024),
            $bytes >= 1024 * 1024 => sprintf('%.1f MB', $bytes / 1024 / 1024),
            $bytes >= 1024 => sprintf('%.1f KB', $bytes / 1024),
            default => $bytes.' B',
        };
    }
}
