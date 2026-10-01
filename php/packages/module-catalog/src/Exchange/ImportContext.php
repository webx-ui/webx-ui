<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Closure;

/**
 * What a codec may know about the run it reads a cell for: whether it may create what it does not
 * find, whether anything is written at all, the default language, and a memory for the length of
 * the run — the reference books as "slug → id", so that 85 thousand rows do not ask the database
 * the same question 85 thousand times (§4.4 of the exchange spec).
 *
 * The memory has to forget what a rolled-back row created: a category made by row 12 that failed
 * is gone from the database, and row 13 must not be filed under its id. A column that creates
 * says so with `made()`, and the whole bag goes when the row fails.
 */
final class ImportContext
{
    /** @var array<string, array<string, mixed>> */
    private array $memo = [];

    /** @var array<string, true> the bags a row now in progress created something in */
    private array $made = [];

    /** The number of the row being read, for whatever reports on it later — a download on the queue. */
    public int $row = 0;

    /**
     * @param  Closure(string): bool  $can  the permissions of whoever started the run, as they were then
     * @param  array<string, mixed>  $options  the run's options, for a column that has one of its own
     */
    public function __construct(
        public readonly bool $createMissing,
        public readonly bool $dryRun,
        public readonly string $defaultLocale,
        private readonly Closure $can,
        public readonly array $options = [],
        public readonly ?int $runId = null,
    ) {}

    public function can(string $permission): bool
    {
        return ($this->can)($permission);
    }

    /**
     * What `$find` answers for this key, asked once a run.
     *
     * @template T
     *
     * @param  Closure(): T  $find
     * @return T
     */
    public function remember(string $bag, string $key, Closure $find): mixed
    {
        if (array_key_exists($key, $this->memo[$bag] ?? [])) {
            /** @var T */
            return $this->memo[$bag][$key];
        }

        return $this->memo[$bag][$key] = $find();
    }

    /** Something in this bag was created by the row now in progress. */
    public function made(string $bag): void
    {
        $this->made[$bag] = true;
    }

    /** A row begins: nothing it creates has been created yet. */
    public function beginRow(): void
    {
        $this->made = [];
    }

    /** The row was rolled back: forget every bag it created something in. */
    public function rowFailed(): void
    {
        foreach (array_keys($this->made) as $bag) {
            unset($this->memo[$bag]);
        }

        $this->made = [];
    }

    /** A chunk was rolled back as a whole — a check without writing: forget everything. */
    public function forget(): void
    {
        $this->memo = [];
        $this->made = [];
    }
}
