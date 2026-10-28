<?php

declare(strict_types=1);

namespace WebxUi\Users\Schema;

use Illuminate\Database\Connection;
use RuntimeException;

/**
 * What the module finds where its table goes (spec §3, decision 13).
 *
 * A site made from the skeleton before this module existed has Laravel's own `users` — `name`
 * and `password` not null — and an empty `password_reset_tokens` beside it. The migration and
 * `webx:doctor` ask the same question here and say the same words about a table that holds rows:
 * whose they are, and whether to keep them, is the site's to decide.
 */
final readonly class UsersTable
{
    public const string ABSENT = 'absent';

    /** Laravel's table of a fresh skeleton, with nothing in it: dropped and made again. */
    public const string LARAVEL_EMPTY = 'laravel-empty';

    public const string OURS = 'ours';

    /** Somebody else's table that holds rows, or one that is not Laravel's: left alone. */
    public const string OCCUPIED = 'occupied';

    public function __construct(private Connection $db) {}

    public function state(): string
    {
        $schema = $this->db->getSchemaBuilder();

        if (! $schema->hasTable('users')) {
            return self::ABSENT;
        }

        if ($schema->hasColumn('users', 'first_name')) {
            return self::OURS;
        }

        return $this->rows() === 0 && $schema->hasColumn('users', 'name') ? self::LARAVEL_EMPTY : self::OCCUPIED;
    }

    public function rows(): int
    {
        return $this->db->table('users')->count();
    }

    /** The refusal: the same sentence in the migration's exception and in `webx:doctor`. */
    public function refusal(): string
    {
        $rows = $this->rows();

        return $rows > 0
            ? "Table `users` already holds {$rows} rows; move them out or drop the table, then migrate again."
            : 'Table `users` is not the one Laravel creates and not this module\'s; drop it, then migrate again.';
    }

    /** Laravel's empty table goes, and its empty `password_reset_tokens` with it. */
    public function dropLaravel(): void
    {
        $schema = $this->db->getSchemaBuilder();
        $schema->drop('users');

        if ($schema->hasTable('password_reset_tokens') && $this->db->table('password_reset_tokens')->count() === 0) {
            $schema->drop('password_reset_tokens');
        }
    }

    /** Before the table is made: make room, or refuse. */
    public function prepare(): void
    {
        match ($this->state()) {
            self::LARAVEL_EMPTY => $this->dropLaravel(),
            self::OCCUPIED => throw new RuntimeException($this->refusal()),
            default => null,
        };
    }
}
