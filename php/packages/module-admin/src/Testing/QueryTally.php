<?php

declare(strict_types=1);

namespace WebxUi\Admin\Testing;

use Countable;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use WebxUi\Admin\Support\SqlShape;

/**
 * The SQL a piece of work ran: how many statements, how long, and which of them repeated.
 *
 * A repeated statement — the same SQL with other bindings, once per row of a list — is what an
 * N+1 looks like from the outside, and the one thing a bare count cannot show. The tally keeps
 * the statements so that a failing budget can print them: the fix starts from knowing which
 * relation was read in a loop, not from the number.
 */
final class QueryTally implements Countable
{
    /**
     * @param  list<array{query: string, bindings: array<int|string, mixed>, time: float|null}>  $queries
     * @param  mixed  $result  what the measured work returned
     */
    public function __construct(
        public readonly array $queries,
        public readonly mixed $result = null,
    ) {}

    /**
     * Run `$work` with the query log on and come back with every statement it issued.
     *
     * The log is turned on around the work and left as it was found: a test that keeps its own
     * log running sees its earlier statements kept, not thrown away, and nothing of the measured
     * work leaks into what it counts afterwards.
     */
    public static function record(callable $work, ?Connection $connection = null): self
    {
        $connection ??= Container::getInstance()->make(DatabaseManager::class)->connection();

        $wasLogging = $connection->logging();
        $before = $wasLogging ? $connection->getQueryLog() : [];

        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $result = $work();
            /** @var list<array{query: string, bindings: array<int|string, mixed>, time: float|null}> $queries */
            $queries = array_values($connection->getQueryLog());
        } finally {
            $connection->flushQueryLog();

            if ($wasLogging) {
                foreach ($before as $entry) {
                    $connection->logQuery($entry['query'], $entry['bindings'], $entry['time'] ?? null);
                }
            } else {
                $connection->disableQueryLog();
            }
        }

        return new self($queries, $result);
    }

    public function count(): int
    {
        return count($this->queries);
    }

    /** Milliseconds the database reported for all statements together. */
    public function time(): float
    {
        return round(array_sum(array_map(static fn (array $query): float => (float) ($query['time'] ?? 0), $this->queries)), 2);
    }

    /**
     * Statements that ran more than once, most repeated first: `shape => times`.
     *
     * Two statements share a shape when they differ only in their bindings — `in (?, ?)` and
     * `in (?, ?, ?)` are one shape too, since eager loading asks the same question with a
     * longer list. Everything a row-by-row loop issues collapses into one line here.
     *
     * @return array<string, int>
     */
    public function repeated(): array
    {
        $shapes = [];

        foreach ($this->queries as $query) {
            $shape = self::shape($query['query']);
            $shapes[$shape] = ($shapes[$shape] ?? 0) + 1;
        }

        $repeated = array_filter($shapes, static fn (int $times): bool => $times > 1);
        arsort($repeated);

        return $repeated;
    }

    /** The statements as the log saw them, for a failure message or a report. */
    public function describe(): string
    {
        $lines = [sprintf('%d quer%s, %s ms', $this->count(), $this->count() === 1 ? 'y' : 'ies', $this->time())];

        foreach ($this->queries as $index => $query) {
            $lines[] = sprintf('%3d. %s', $index + 1, self::inline($query['query'], $query['bindings']));
        }

        $repeated = $this->repeated();

        if ($repeated !== []) {
            $lines[] = '';
            $lines[] = 'Repeated (an N+1 looks like this):';

            foreach ($repeated as $shape => $times) {
                $lines[] = sprintf('  %d× %s', $times, $shape);
            }
        }

        return implode(PHP_EOL, $lines);
    }

    /** The SQL with its bindings-only differences removed. */
    public static function shape(string $sql): string
    {
        return SqlShape::of($sql);
    }

    /**
     * The statement with its bindings written in, roughly: good enough to read, not to run.
     *
     * @param  array<int|string, mixed>  $bindings
     */
    private static function inline(string $sql, array $bindings): string
    {
        $values = array_values($bindings);
        $index = 0;

        return preg_replace_callback('/\?/', static function () use ($values, &$index): string {
            if (! array_key_exists($index, $values)) {
                return '?';
            }

            $value = $values[$index++];

            return match (true) {
                $value === null => 'null',
                is_bool($value) => $value ? 'true' : 'false',
                is_int($value), is_float($value) => (string) $value,
                $value instanceof \DateTimeInterface => "'".$value->format('Y-m-d H:i:s')."'",
                is_string($value) => "'".(mb_strlen($value) > 40 ? mb_substr($value, 0, 37).'…' : $value)."'",
                default => '?',
            };
        }, $sql) ?? $sql;
    }
}
