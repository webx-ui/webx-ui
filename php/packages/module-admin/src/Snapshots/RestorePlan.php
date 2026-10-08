<?php

declare(strict_types=1);

namespace WebxUi\Admin\Snapshots;

/**
 * What a restore is about to do, worked out before it does any of it — so that the question it
 * asks names real numbers, and a refusal comes before the backup rather than after it.
 */
final class RestorePlan
{
    /**
     * @param  list<string>  $replace  Tables whose rows are replaced by the archive's.
     * @param  list<string>  $kept  Tables in the archive that stay as they are here under these options.
     * @param  list<string>  $missing  Tables in the archive this stand does not have.
     * @param  list<string>  $untouched  Travelling tables here the archive does not have (a module the source lacks).
     * @param  list<string>  $derived  Tables emptied so they are rebuilt from the new content.
     * @param  list<string>  $undeclared  Tables here nobody declared; travel only with `--all`.
     * @param  list<string>  $unknownMigrations  In the archive, unknown to this code: refuse.
     * @param  list<string>  $pendingMigrations  Known to this code, not run here yet: run before the import.
     * @param  list<string>  $newerHere  Run here, not in the archive: the archive is older.
     * @param  int  $rows  Rows the archive holds for `$replace`.
     * @param  int  $files  Files the archive brings, when media is restored.
     * @param  int  $deletions  Files here a mirror would delete.
     */
    public function __construct(
        public readonly array $replace,
        public readonly array $kept,
        public readonly array $missing,
        public readonly array $untouched,
        public readonly array $derived,
        public readonly array $undeclared,
        public readonly array $unknownMigrations,
        public readonly array $pendingMigrations,
        public readonly array $newerHere,
        public readonly int $rows,
        public readonly bool $media,
        public readonly int $files,
        public readonly int $deletions,
    ) {}
}
