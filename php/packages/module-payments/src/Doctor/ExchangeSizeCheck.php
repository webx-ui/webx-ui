<?php

declare(strict_types=1);

namespace WebxUi\Payments\Doctor;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Payments\Console\ExchangePruneCommand;
use WebxUi\Payments\Exchange\ExchangeConnection;
use WebxUi\Payments\Exchange\ExchangeJournal;

/**
 * The size of the exchange journal in `webx:doctor` (spec §20.4): it only grows — nothing is
 * pruned by itself (decision 31) — so past `webx-payments.exchange.warn_size` the doctor says so and
 * names the command. A note, never a reason to stop a deploy: the journal working is the point.
 *
 * Registered only where the panel's frame is installed: payments do not need it.
 */
final class ExchangeSizeCheck implements Check
{
    private const string SUBJECT = 'Payment exchange journal';

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly ExchangeConnection $connection,
        private readonly Repository $config,
    ) {}

    public function run(): array
    {
        $limit = $this->config->get('webx-payments.exchange.warn_size', 1024 * 1024 * 1024);
        $limit = is_numeric($limit) ? (int) $limit : 1024 * 1024 * 1024;

        try {
            $size = $this->size();
        } catch (Throwable) {
            return [Diagnosis::warn(self::SUBJECT, 'The table '.ExchangeJournal::TABLE.' cannot be read: run php artisan migrate.')];
        }

        if ($size <= $limit) {
            return [];
        }

        return [Diagnosis::note(self::SUBJECT, sprintf(
            'The journal takes %.1f MB, more than the %.1f MB of webx-payments.exchange.warn_size. Nothing is removed by itself; to remove events older than a year: php artisan webx:payments:exchange-prune --days=%d.',
            $size / 1024 / 1024,
            $limit / 1024 / 1024,
            ExchangePruneCommand::MIN_DAYS,
        ))];
    }

    /** Bytes on disk where the database says it, the bodies' bytes elsewhere. */
    private function size(): int
    {
        $connection = $this->db->connection($this->connection->name());

        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $row = $connection->selectOne(
                'SELECT data_length + index_length AS size FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = ?',
                [$connection->getTablePrefix().ExchangeJournal::TABLE],
            );

            return is_object($row) && isset($row->size) && is_numeric($row->size) ? (int) $row->size : 0;
        }

        $sum = $connection->table(ExchangeJournal::TABLE)->sum($connection->raw('COALESCE(LENGTH(request_body), 0) + COALESCE(LENGTH(response_body), 0)'));

        return is_numeric($sum) ? (int) $sum : 0;
    }
}
