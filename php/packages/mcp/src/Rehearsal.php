<?php

declare(strict_types=1);

namespace WebxUi\Mcp;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;

/**
 * A dry run that is the real call, undone.
 *
 * A dry run that only echoes what it was sent says yes to an address that is taken, a rating of
 * seven, an id that does not exist — and the real call then says no. Running the real write in a
 * transaction and rolling it back puts the dry run through every check the write has: the screen's
 * rules, the registry of addresses, the guards of the model. And what it answers is what the real
 * call would have answered.
 */
final class Rehearsal
{
    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @param  string|null  $connection  The connection the work writes through; the default one when null.
     * @return T
     */
    public static function run(Closure $work, ?string $connection = null): mixed
    {
        /** @var DatabaseManager $db */
        $db = Container::getInstance()->make('db');

        return self::on($db->connection($connection), $work);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public static function on(ConnectionInterface $connection, Closure $work): mixed
    {
        $connection->beginTransaction();

        try {
            return $work();
        } finally {
            $connection->rollBack();
        }
    }
}
