<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use WebxUi\Tasks\Workflow\StageStatus;
use WebxUi\Tasks\Workflow\TaskStatus;

/**
 * The tables of §3, cell by cell: every move a stage may make, every move it may not, and the
 * status a task's stages add up to.
 */
final class StatusTableTest extends PhpUnitTestCase
{
    /** Every move §3 draws, plus «ready → pending», which only a reorder makes. */
    private const array ALLOWED = [
        'pending' => ['ready', 'cancelled'],
        'ready' => ['in_progress', 'pending', 'cancelled'],
        'in_progress' => ['review', 'blocked', 'ready', 'rework', 'cancelled'],
        'review' => ['done', 'rework', 'cancelled'],
        'rework' => ['in_progress', 'cancelled'],
        'blocked' => ['ready', 'cancelled'],
        'done' => [],
        'cancelled' => [],
    ];

    /**
     * @return iterable<string, array{StageStatus, StageStatus, bool}>
     */
    public static function moves(): iterable
    {
        foreach (StageStatus::cases() as $from) {
            foreach (StageStatus::cases() as $to) {
                yield "{$from->value} → {$to->value}" => [$from, $to, in_array($to->value, self::ALLOWED[$from->value], true)];
            }
        }
    }

    #[Test]
    #[DataProvider('moves')]
    public function a_stage_moves_only_as_the_table_says(StageStatus $from, StageStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canBecome($to));
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function tasks(): iterable
    {
        yield 'nothing started' => [['ready', 'pending'], 'ready'];
        yield 'a stage taken' => [['in_progress', 'pending'], 'in_progress'];
        yield 'a stage sent back' => [['rework', 'pending'], 'in_progress'];
        yield 'a stage waiting for an answer' => [['blocked', 'pending'], 'in_progress'];
        yield 'a stage reported' => [['done', 'review', 'pending'], 'review'];
        yield 'work beats review' => [['review', 'in_progress'], 'in_progress'];
        yield 'all done' => [['done', 'done'], 'done'];
        yield 'done but for the cancelled' => [['done', 'cancelled'], 'done'];
        yield 'the first done, the next open' => [['done', 'ready'], 'ready'];
    }

    /**
     * @param  list<string>  $stages
     */
    #[Test]
    #[DataProvider('tasks')]
    public function a_task_is_what_its_stages_add_up_to(array $stages, string $expected): void
    {
        $this->assertSame($expected, TaskStatus::of(array_map(StageStatus::from(...), $stages))->value);
    }
}
