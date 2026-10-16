<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Admin\Testing\QueryTally;
use WebxUi\Admin\Tests\Fixtures\Ticket;

/**
 * The query budget other packages' tests stand on: it counts what ran, it hands the work's
 * result back, a failure names every statement and the repeated ones, and the test's own query
 * log is left the way it was found.
 */
final class QueryBudgetTest extends TestCase
{
    use CountsQueries;

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('subject');
            $table->timestamps();
        });
    }

    #[Test]
    public function the_tally_counts_what_the_work_ran_and_keeps_its_result(): void
    {
        Ticket::query()->create(['subject' => 'One']);
        Ticket::query()->create(['subject' => 'Two']);

        $tally = $this->recordQueries(static fn (): int => Ticket::query()->count() + Ticket::query()->count());

        $this->assertSame(2, $tally->count());
        $this->assertSame(4, $tally->result);
        $this->assertSame(['select count(*) as "aggregate" from "tickets"' => 2], $tally->repeated());
        $this->assertStringContainsString('2 queries', $tally->describe());
        $this->assertStringContainsString('2× select count(*) as "aggregate" from "tickets"', $tally->describe());
    }

    #[Test]
    public function a_budget_that_holds_passes_and_hands_the_result_back(): void
    {
        Ticket::query()->create(['subject' => 'One']);

        $subject = $this->assertQueriesWithin(1, static fn (): ?string => Ticket::query()->value('subject'));

        $this->assertSame('One', $subject);
        $this->assertNoRepeatedQueries(static fn (): int => Ticket::query()->count());
    }

    #[Test]
    public function a_budget_that_breaks_lists_the_statements_and_the_repeated_ones(): void
    {
        foreach (['One', 'Two', 'Three'] as $subject) {
            Ticket::query()->create(['subject' => $subject]);
        }

        // The classic N+1: a list, then one statement per row.
        $loop = static function (): void {
            foreach (Ticket::query()->pluck('id') as $id) {
                Ticket::query()->whereKey($id)->first();
            }
        };

        try {
            $this->assertQueriesWithin(2, $loop);
        } catch (AssertionFailedError $failure) {
            $message = $failure->getMessage();

            $this->assertStringContainsString('Over the budget of 2 queries.', $message);
            $this->assertStringContainsString('4 queries', $message);
            $this->assertStringContainsString('where "tickets"."id" = 1 limit 1', $message, 'bindings are written in');
            $this->assertStringContainsString('Repeated (an N+1 looks like this):', $message);
            $this->assertStringContainsString('3× select * from "tickets" where "tickets"."id" = ? limit 1', $message);

            return;
        }

        $this->fail('Four queries should not fit a budget of two.');
    }

    #[Test]
    public function a_repeated_statement_fails_the_no_repeat_assertion_whatever_the_count(): void
    {
        Ticket::query()->create(['subject' => 'One']);

        try {
            $this->assertNoRepeatedQueries(static function (): void {
                Ticket::query()->count();
                Ticket::query()->count();
            });
        } catch (AssertionFailedError $failure) {
            $this->assertStringContainsString('A statement ran more than once.', $failure->getMessage());

            return;
        }

        $this->fail('The same statement twice is a repeat.');
    }

    #[Test]
    public function in_lists_of_any_length_are_one_shape(): void
    {
        $this->assertSame(
            QueryTally::shape('select * from "tickets" where "id" in (?, ?, ?)'),
            QueryTally::shape("select * from \"tickets\"\n where \"id\" in (?)"),
        );
    }

    #[Test]
    public function the_tests_own_query_log_is_left_as_it_was_found(): void
    {
        $this->recordQueries(static fn (): int => Ticket::query()->count());
        $this->assertFalse(DB::connection()->logging(), 'a log that was off stays off');

        DB::enableQueryLog();
        Ticket::query()->count();

        $this->recordQueries(static fn (): int => Ticket::query()->count() + Ticket::query()->count());

        $this->assertTrue(DB::connection()->logging(), 'a log that was on stays on');
        $this->assertCount(1, DB::getQueryLog(), 'with what it held before, and nothing of the measured work');

        DB::disableQueryLog();
    }
}
