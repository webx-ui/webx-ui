<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Tasks\Models\JournalEntry;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Models\Task;
use WebxUi\Tasks\Workflow\JournalKind;
use WebxUi\Tasks\Workflow\Priority;
use WebxUi\Tasks\Workflow\StageStatus;

/**
 * What may still change once a task is out of draft (§3): the stages nobody has started, and
 * new stages at the end. Everything else stays as the agents read it.
 */
final class EditingTest extends TestCase
{
    #[Test]
    public function a_draft_is_rewritten_freely(): void
    {
        $task = $this->task(stages: ['One', 'Two', 'Three'], ready: false);
        [$one, , $three] = $task->stages->all();

        $task = $this->tasks()->update($task, ['title' => 'Checkout', 'spec' => 'New spec.'], [
            ['id' => $three->id, 'title' => 'Three first'],
            ['title' => 'Brand new', 'review_mode' => 'human'],
            ['id' => $one->id],
        ], $this->person());

        $this->assertSame('Checkout', $task->title);
        $this->assertSame(['Three first', 'Brand new', 'One'], $task->stages->pluck('title')->all());
        $this->assertSame([1, 2, 3], $task->stages->pluck('position')->all());
        $this->assertSame('human', $task->stages[1]->review_mode->value);
        $this->assertSame(3, Stage::query()->count(), 'The stage left out of the draft was kept.');
    }

    #[Test]
    public function after_ready_the_task_keeps_everything_but_its_priority(): void
    {
        $task = $this->task();

        $this->assertRefused('task-started', fn () => $this->tasks()->update($task, ['spec' => 'Changed under the agents.'], null, $this->person()));

        $this->assertSame(Priority::High, $this->tasks()->update($task, ['priority' => 'high'], null, $this->person())->priority);
    }

    #[Test]
    public function stages_nobody_started_can_be_rewritten_and_reordered(): void
    {
        $task = $this->task(stages: ['One', 'Two', 'Three']);
        $one = $this->claim();
        [, $two, $three] = $task->stages->all();

        $task = $this->tasks()->update($task, [], [
            ['id' => $one?->id],
            ['id' => $three->id, 'brief' => 'Sooner.'],
            ['id' => $two->id],
            ['title' => 'Four'],
        ], $this->person());

        $this->assertSame(['One', 'Three', 'Two', 'Four'], $task->stages->pluck('title')->all());
        $this->assertSame('Sooner.', $task->stages[1]->brief);
        $this->assertSame(StageStatus::Pending, $task->stages[3]->status);
    }

    #[Test]
    public function a_reorder_opens_the_stage_that_comes_first(): void
    {
        $task = $this->task(stages: ['One', 'Two']);
        [$one, $two] = $task->stages->all();

        $task = $this->tasks()->update($task, [], [['id' => $two->id], ['id' => $one->id]], $this->person());

        $this->assertSame(['Two' => 'ready', 'One' => 'pending'], $task->stages->mapWithKeys(
            static fn (Stage $stage): array => [$stage->title => $stage->status->value],
        )->all());
        $this->assertSame('Two', $this->claim()?->title);
    }

    #[Test]
    public function a_started_stage_neither_changes_nor_moves(): void
    {
        $task = $this->task(stages: ['One', 'Two']);
        $one = $this->claim();
        $two = $this->stage($task, 2);

        $this->assertRefused('stage-started', fn () => $this->tasks()->update($task, [], [
            ['id' => $one?->id, 'brief' => 'Something else.'],
            ['id' => $two->id],
        ], $this->person()));

        $this->assertRefused('stage-started-order', fn () => $this->tasks()->update($task, [], [
            ['id' => $two->id],
            ['id' => $one?->id],
        ], $this->person()));
    }

    #[Test]
    public function after_ready_no_stage_is_dropped_and_new_ones_go_last(): void
    {
        $task = $this->task(stages: ['One', 'Two']);
        [$one, $two] = $task->stages->all();

        $this->assertRefused('stage-missing', fn () => $this->tasks()->update($task, [], [['id' => $one->id]], $this->person()));
        $this->assertRefused('stage-new-at-end', fn () => $this->tasks()->update($task, [], [
            ['id' => $one->id],
            ['title' => 'Squeezed in'],
            ['id' => $two->id],
        ], $this->person()));
    }

    #[Test]
    public function a_stage_of_another_task_is_refused(): void
    {
        $task = $this->task(stages: ['One'], ready: false);
        $other = $this->task(stages: ['Elsewhere'], ready: false);

        $this->assertRefused('stage-elsewhere', fn () => $this->tasks()->update($task, [], [['id' => $other->stages[0]->id]], $this->person()));
    }

    #[Test]
    public function a_stage_added_to_a_finished_run_opens_at_once(): void
    {
        $task = $this->task(stages: ['One', 'Two']);
        $this->tasks()->cancelStage($this->stage($task, 2), $this->person());
        $this->assertSame(StageStatus::Ready, $this->stage($task, 1)->status);

        [$one, $two] = $task->stages()->get()->all();
        $task = $this->tasks()->update($task, [], [['id' => $one->id], ['id' => $two->id], ['title' => 'Three']], $this->person());

        $this->assertSame(StageStatus::Pending, $this->stage($task, 3)->status);
    }

    #[Test]
    public function a_closed_task_takes_no_edit(): void
    {
        $task = $this->tasks()->cancel($this->task(), $this->person());

        $this->assertRefused('task-closed', fn () => $this->tasks()->update($task, ['priority' => 'low'], null, $this->person()));
    }

    #[Test]
    public function an_edit_is_journalled_with_what_changed(): void
    {
        $task = $this->task(stages: ['One', 'Two']);
        [$one, $two] = $task->stages->all();

        $this->tasks()->update($task, ['priority' => 'high'], [['id' => $one->id, 'title' => 'First'], ['id' => $two->id]], $this->person());

        $entry = JournalEntry::query()->where('task_id', $task->id)->where('kind', JournalKind::Edit)->sole();
        $this->assertSame(['fields' => ['priority'], 'stages' => ['changed #1']], $entry->meta);
        $this->assertSame($this->person()->name, $entry->actor_name);
    }

    #[Test]
    public function an_edit_that_changes_nothing_writes_nothing(): void
    {
        $task = $this->task();
        $entries = JournalEntry::query()->count();

        $this->tasks()->update($task, ['priority' => 'normal'], null, $this->person());

        $this->assertSame($entries, JournalEntry::query()->count());
        $this->assertInstanceOf(Task::class, $task->refresh());
    }
}
