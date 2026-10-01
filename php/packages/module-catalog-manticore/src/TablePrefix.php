<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore;

use LogicException;

/**
 * `MANTICORE_TABLE_PREFIX` (decision 3 of the Manticore spec): required, with no default, and
 * `[a-z0-9_]`. It cannot be guessed from `APP_NAME` — two copies of one site on one server would
 * guess alike and overwrite each other's tables in silence.
 */
final class TablePrefix
{
    public const PATTERN = '/^[a-z0-9][a-z0-9_]{0,47}$/';

    public static function of(mixed $configured): string
    {
        $prefix = is_string($configured) ? trim($configured) : '';

        if ($prefix === '') {
            throw new LogicException('MANTICORE_TABLE_PREFIX is not set: the catalogue will not guess whose tables on the server are its own. Set it to a name of this site, `[a-z0-9_]`.');
        }

        if (preg_match(self::PATTERN, $prefix) !== 1) {
            throw new LogicException("MANTICORE_TABLE_PREFIX is `[a-z0-9_]`, starting with a letter or a digit; [{$prefix}] is not.");
        }

        return rtrim($prefix, '_');
    }
}
