<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Tests;

use LogicException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Admin\Snapshots\TableGroup;
use WebxUi\Tasks\Models\JournalEntry;
use WebxUi\Tasks\Workflow\JournalKind;

/**
 * The journal is the history a person trusts: every change of status in it, with its author, and
 * nothing in it ever rewritten.
 */
final class JournalTest extends TestCase
{
    #[Test]
    public function every_change_of_status_is_written_down_with_who_made_it(): void
    {
        $task = $this->task(stages: ['One']);
        $stage = $this->claim('worker-1');
        $this->tasks()->block($stage, 'Which one?', $this->agent('worker-1'));
        $this->tasks()->unblock($stage, 'The first.', $this->person());
        $stage = $this->claim('worker-2');
        $this->tasks()->report($stage, 'Done.', 'abc', $this->agent('worker-2'));
        $this->tasks()->rework($stage, 'Not quite.', $this->agent('dispatcher'));
        $stage = $this->claim('worker-3');
        $this->tasks()->report($stage, 'Now done.', 'def', $this->agent('worker-3'));
        $this->tasks()->accept($stage, $this->agent('dispatcher'));

        $person = $this->person()->name;

        $lines = $task->journal()->get()->map(static fn (JournalEntry $entry): string => sprintf(
            '%s %s %s:%s→%s',
            $entry->stage_id === null ? 'task' : 'stage',
            $entry->actor_name,
            $entry->kind->value,
            $entry->meta['from'] ?? '-',
            $entry->meta['to'] ?? '-',
        ))->all();

        $this->assertSame([
            "task {$person} status:-→draft",
            "task {$person} status:draft→ready",
            'stage system status:pending→ready',
            'stage worker-1 status:ready→in_progress',
            'task worker-1 status:ready→in_progress',
            'stage worker-1 question:in_progress→blocked',
            "stage {$person} answer:blocked→ready",
            "task {$person} status:in_progress→ready",
            'stage worker-2 status:ready→in_progress',
            'task worker-2 status:ready→in_progress',
            'stage worker-2 report:in_progress→review',
            'task worker-2 status:in_progress→review',
            'stage dispatcher rework:review→rework',
            'task dispatcher status:review→in_progress',
            'stage worker-3 status:rework→in_progress',
            'stage worker-3 report:in_progress→review',
            'task worker-3 status:in_progress→review',
            'stage dispatcher status:review→done',
            'task dispatcher status:review→done',
        ], $lines);
    }

    #[Test]
    public function a_comment_goes_to_the_task_or_one_of_its_stages(): void
    {
        $task = $this->task(stages: ['One']);
        $stage = $this->stage($task, 1);

        $this->tasks()->comment($task, null, 'On the whole task.', $this->person());
        $this->tasks()->comment($task, $stage, 'On the stage.', $this->agent());

        $comments = $task->journal()->where('kind', JournalKind::Comment)->get();
        $this->assertSame([null, $stage->id], $comments->pluck('stage_id')->all());
        $this->assertRefused('text-required', fn () => $this->tasks()->comment($task, null, ' ', $this->person()));
        $this->assertRefused('stage-elsewhere', fn () => $this->tasks()->comment($this->task(), $stage, 'Wrong task.', $this->person()));
    }

    #[Test]
    public function a_line_of_the_journal_is_never_changed(): void
    {
        $entry = $this->task()->journal()->firstOrFail();

        $this->expectException(LogicException::class);

        $entry->update(['body' => 'Rewritten history.']);
    }

    #[Test]
    public function a_line_of_the_journal_is_never_deleted(): void
    {
        $entry = $this->task()->journal()->firstOrFail();

        try {
            $entry->delete();
            $this->fail('A journal line was deleted.');
        } catch (LogicException) {
            $this->assertTrue(JournalEntry::query()->whereKey($entry->id)->exists());
        }
    }

    #[Test]
    public function the_section_and_its_tables_are_declared(): void
    {
        $module = $this->app->make(ModuleRegistry::class)->get('tasks');

        $this->assertSame(['tasks.view', 'tasks.manage', 'tasks.work', 'tasks.review'], $module->permissions());
        $this->assertSame('Tasks', $module->title());

        $tables = $this->app->make(SnapshotTables::class);

        foreach (['tasks_projects', 'tasks_tasks', 'tasks_stages', 'tasks_journal'] as $table) {
            $this->assertSame(TableGroup::Content, $tables->groupOf($table), $table);
        }
    }
}
