<?php

declare(strict_types=1);

namespace WebxUi\Users\Doctor;

use Illuminate\Database\Connection;
use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Users\Schema\UsersTable;

/**
 * The table of visitors in `webx:doctor` (spec §3, decision 13): a `users` that is somebody
 * else's and holds rows stops the migration of the module, and this says so in the migration's
 * own words before a deploy runs into it.
 */
final readonly class UsersTableCheck implements Check
{
    private const string SUBJECT = 'Visitors';

    public function __construct(private Connection $db) {}

    public function run(): array
    {
        try {
            $table = new UsersTable($this->db);
            $state = $table->state();
        } catch (Throwable) {
            // No database to look at: the database's own line says that.
            return [];
        }

        return match ($state) {
            UsersTable::OURS => [Diagnosis::ok(self::SUBJECT, 'table `users` is the module\'s.')],
            UsersTable::OCCUPIED => [Diagnosis::fail(self::SUBJECT, $table->refusal())],
            // Not migrated yet: `php artisan migrate` makes it, over an empty table of Laravel too.
            default => [Diagnosis::warn(self::SUBJECT, 'table `users` is not migrated yet; run php artisan migrate.')],
        };
    }
}
