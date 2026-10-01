<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Closure;

/**
 * A reference book as "code → id" for the length of a run (§4.4 of the exchange spec): read once
 * by the first row that asks, added to when the import creates, and forgotten with its bag when the
 * row that created something fails — the next row reads it again.
 *
 * The codes are the caller's: lowercase them before both `find()` and `add()` when case does not
 * count.
 */
final class Lookup
{
    /**
     * @param  array<string, int>  $ids
     */
    public function __construct(private array $ids) {}

    /**
     * The book of this run under `$bag`, read with `$read` the first time.
     *
     * @param  Closure(): array<string, int>  $read
     */
    public static function of(ImportContext $context, string $bag, Closure $read): self
    {
        return $context->remember($bag, 'lookup', static fn (): self => new self($read()));
    }

    public function find(string $code): ?int
    {
        return $this->ids[$code] ?? null;
    }

    /** Something the row created: say so to the context too ({@see ImportContext::made()}). */
    public function add(string $code, int $id): void
    {
        $this->ids[$code] = $id;
    }

    /**
     * What a refusal lists as "known".
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map('strval', array_keys($this->ids));
    }
}
