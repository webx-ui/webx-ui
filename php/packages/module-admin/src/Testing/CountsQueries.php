<?php

declare(strict_types=1);

namespace WebxUi\Admin\Testing;

use PHPUnit\Framework\TestCase;

/**
 * A query budget for a test, in any package's Testbench case:
 *
 *     use CountsQueries;
 *
 *     $response = $this->assertQueriesWithin(12, fn () => $this->get('/blog'));
 *
 * The work runs once, its result comes back, and the test fails when the database was asked
 * more than the budget allows — with every statement listed and the repeated ones called out,
 * which is where the N+1 is. A budget is a ceiling the page is known to fit under today, not a
 * target: when a change needs more, the change explains why in the test before raising it.
 *
 * `recordQueries()` is the same measurement without the assertion, for a test that wants to
 * read the tally itself — compare two paths, or assert that nothing repeated.
 *
 * @phpstan-require-extends TestCase
 */
trait CountsQueries
{
    /**
     * Run `$work` and fail when it issued more than `$budget` statements.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $work
     * @return TResult what `$work` returned
     */
    protected function assertQueriesWithin(int $budget, callable $work, string $message = ''): mixed
    {
        $tally = QueryTally::record($work);

        static::assertLessThanOrEqual(
            $budget,
            $tally->count(),
            rtrim($message === '' ? "Over the budget of {$budget} queries." : $message, PHP_EOL).PHP_EOL.$tally->describe(),
        );

        return $tally->result;
    }

    /** Run `$work` and fail when any statement ran more than once — an N+1 regardless of the count. */
    protected function assertNoRepeatedQueries(callable $work, string $message = ''): mixed
    {
        $tally = QueryTally::record($work);

        static::assertSame(
            [],
            $tally->repeated(),
            rtrim($message === '' ? 'A statement ran more than once.' : $message, PHP_EOL).PHP_EOL.$tally->describe(),
        );

        return $tally->result;
    }

    /** Run `$work` and come back with what the database was asked; asserts nothing. */
    protected function recordQueries(callable $work): QueryTally
    {
        return QueryTally::record($work);
    }
}
