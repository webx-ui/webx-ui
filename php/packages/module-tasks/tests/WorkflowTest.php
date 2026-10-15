<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Tests;

use Closure;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Models\Task;
use WebxUi\Tasks\Workflow\StageStatus;
use WebxUi\Tasks\Workflow\TaskStatus;

/**
 * A task from draft to done, and every door on the way that has to stay shut.
 */
final class WorkflowTest extends TestCase
{
    #[Test]
    public function a_task_is_written_with_its_stages_in_one_go_as_a_draft(): void
    {
        $project = $this->project();
        $first = $this->task($project, ['Schema', 'Screens', 'Docs'], ready: false);
        $second = $this->task($project, ['Only'], ready: false);

        $this->assertSame(TaskStatus::Draft, $first->status);
        $this->assertSame([1, 2], [$first->number, $second->number]);
        $this->assertSame(['Schema', 'Screens', 'Docs'], $first->stages->pluck('title')->all());
        $this->assertSame([1, 2, 3], $first->stages->pluck('position')->all());
        $this->assertTrue($first->stages->every(static fn (Stage $stage): bool => $stage->status === StageStatus::Pending));
        $this->assertSame($this->person()->adminId, $first->created_by);
    }

    #[Test]
    public function the_number_is_counted_inside_the_project(): void
    {
        $this->task($this->project('shop'), ready: false);
        $other = $this->task($this->project('blog'), ready: false);

        $this->assertSame(1, $other->number);
    }

    #[Test]
    public function a_stage_without_a_title_is_refused_and_nothing_is_written(): void
    {
        $project = $this->project();

        try {
            $this->tasks()->create($project, ['title' => 'Broken'], [['title' => 'Fine'], ['brief' => 'No title']], $this->person());
            $this->fail('A stage without a title was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('stages.1.title', $exception->errors());
        }

        $this->assertSame(0, Task::query()->count());
    }

    #[Test]
    public function ready_opens_the_first_stage_only(): void
    {
        $task = $this->task(stages: ['One', 'Two']);

        $this->assertSame(TaskStatus::Ready, $task->status);
        $this->assertSame(StageStatus::Ready, $this->stage($task, 1)->status);
        $this->assertSame(StageStatus::Pending, $this->stage($task, 2)->status);
    }

    #[Test]
    public function a_task_without_stages_cannot_be_made_ready(): void
    {
        $task = $this->task(stages: [], ready: false);

        $this->assertRefused('task-no-stages', fn () => $this->tasks()->ready($task, $this->person()));
    }

    #[Test]
    public function only_a_draft_is_made_ready(): void
    {
        $task = $this->task();

        $this->assertRefused('task-not-draft', fn () => $this->tasks()->ready($task, $this->person()));
    }

    #[Test]
    public function stages_go_one_after_another_until_the_task_is_done(): void
    {
        $task = $this->task(stages: ['One', 'Two']);

        $one = $this->claim('worker-1');
        $this->assertSame('One', $one?->title);
        $this->assertSame(TaskStatus::InProgress, $task->refresh()->status);

        // The second stage is not handed out while the first is open.
        $this->assertNull($this->claim('worker-2'));

        $this->tasks()->report($one, "## Done\nAll of it.", 'feat/cart@abc123', $this->agent('worker-1'));
        $this->assertSame(TaskStatus::Review, $task->refresh()->status);

        $one = $this->tasks()->accept($one, $this->agent('dispatcher'));
        $this->assertSame(StageStatus::Done, $one->status);
        $this->assertNotNull($one->accepted_at);
        $this->assertSame(StageStatus::Ready, $this->stage($task, 2)->status);
        $this->assertSame(TaskStatus::Ready, $task->refresh()->status);

        $two = $this->claim('worker-2');
        $this->assertSame('Two', $two?->title);
        $this->tasks()->report($two, 'Done.', null, $this->agent('worker-2'));
        $this->tasks()->accept($two, $this->agent('dispatcher'));

        $task->refresh();
        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertNotNull($task->closed_at);
    }

    #[Test]
    public function rework_counts_the_attempt_and_the_next_agent_gets_the_remarks(): void
    {
        $this->task(stages: ['One']);

        $stage = $this->claim('worker-1');
        $this->tasks()->report($stage, 'First try.', null, $this->agent('worker-1'));
        $stage = $this->tasks()->rework($stage, 'The empty cart crashes.', $this->agent('dispatcher'));

        $this->assertSame(StageStatus::Rework, $stage->status);
        $this->assertSame(2, $stage->attempt);

        $package = $this->dispatcher()->claimNext($this->agent('worker-2'));

        $this->assertSame(2, $package['stage']['attempt'] ?? null);
        $this->assertSame('The empty cart crashes.', $package['stage']['rework_remarks'] ?? null);
    }

    #[Test]
    public function remarks_are_required_to_send_a_stage_back(): void
    {
        $this->task(stages: ['One']);
        $stage = $this->claim();
        $this->tasks()->report($stage, 'Done.', null, $this->agent());

        $this->assertRefused('text-required', fn () => $this->tasks()->rework($stage, '  ', $this->agent('dispatcher')));
    }

    #[Test]
    public function a_released_stage_goes_back_where_it_was_taken_from(): void
    {
        $this->task(stages: ['One']);

        $stage = $this->claim('worker-1');
        $stage = $this->tasks()->release($stage, $this->agent('dispatcher'), 'Hung for an hour.');
        $this->assertSame(StageStatus::Ready, $stage->status);
        $this->assertNull($stage->assignee);

        $stage = $this->claim('worker-2');
        $this->tasks()->report($stage, 'Done.', null, $this->agent('worker-2'));
        $this->tasks()->rework($stage, 'Again.', $this->agent('dispatcher'));
        $stage = $this->claim('worker-3');

        $this->assertSame(StageStatus::Rework, $this->tasks()->release($stage, $this->person())->status);
    }

    #[Test]
    public function a_question_blocks_the_stage_until_a_person_answers_and_the_answer_travels(): void
    {
        $task = $this->task(stages: ['One']);

        $stage = $this->claim('worker-1');
        $stage = $this->tasks()->block($stage, 'Which payment provider?', $this->agent('worker-1'));

        $this->assertSame(StageStatus::Blocked, $stage->status);
        $this->assertSame(TaskStatus::InProgress, $task->refresh()->status);
        $this->assertNull($this->claim('worker-2'), 'A blocked stage was handed out.');

        $this->assertRefused('person-only', fn () => $this->tasks()->unblock($stage, 'Any.', $this->agent('dispatcher')));

        $this->tasks()->unblock($stage, 'The one already configured.', $this->person());
        $package = $this->dispatcher()->claimNext($this->agent('worker-2'));

        $this->assertSame([[
            'question' => 'Which payment provider?',
            'asked_by' => 'worker-1',
            'answer' => 'The one already configured.',
            'answered_by' => $this->person()->name,
        ]], $package['stage']['questions'] ?? null);
    }

    #[Test]
    public function an_agent_reports_and_asks_only_about_the_stage_it_holds(): void
    {
        $this->task(stages: ['One']);
        $stage = $this->claim('worker-1');

        $this->assertRefused('not-holder', fn () => $this->tasks()->report($stage, 'Mine now.', null, $this->agent('worker-2')));
        $this->assertRefused('not-holder', fn () => $this->tasks()->block($stage, 'Why?', $this->agent('worker-2')));
    }

    #[Test]
    public function an_agent_cannot_review_its_own_work(): void
    {
        $this->task(stages: ['One']);
        $stage = $this->claim('worker-1');
        $this->tasks()->report($stage, 'Done.', null, $this->agent('worker-1'));

        $this->assertRefused('own-work', fn () => $this->tasks()->accept($stage, $this->agent('worker-1')));
    }

    #[Test]
    public function a_stage_marked_human_is_decided_by_a_person_only(): void
    {
        $this->task(stages: ['Design'], human: ['Design']);
        $stage = $this->claim('worker-1');
        $this->tasks()->report($stage, 'Done.', null, $this->agent('worker-1'));

        $this->assertRefused('human-review', fn () => $this->tasks()->accept($stage, $this->agent('dispatcher')));
        $this->assertRefused('human-review', fn () => $this->tasks()->rework($stage, 'No.', $this->agent('dispatcher')));

        $this->assertSame(StageStatus::Done, $this->tasks()->accept($stage, $this->person())->status);
    }

    /**
     * @return iterable<string, array{string, Closure(self, Stage): mixed}>
     */
    public static function outOfTurn(): iterable
    {
        yield 'report before taking' => ['ready', static fn (self $test, Stage $stage) => $test->tasks()->report($stage, 'Done.', null, $test->person())];
        yield 'accept before a report' => ['in_progress', static fn (self $test, Stage $stage) => $test->tasks()->accept($stage, $test->person())];
        yield 'rework before a report' => ['in_progress', static fn (self $test, Stage $stage) => $test->tasks()->rework($stage, 'No.', $test->person())];
        yield 'release what is not taken' => ['ready', static fn (self $test, Stage $stage) => $test->tasks()->release($stage, $test->person())];
        yield 'answer what was not asked' => ['in_progress', static fn (self $test, Stage $stage) => $test->tasks()->unblock($stage, 'Yes.', $test->person())];
        yield 'block a reported stage' => ['review', static fn (self $test, Stage $stage) => $test->tasks()->block($stage, 'Why?', $test->person())];
    }

    /**
     * @param  Closure(self, Stage): mixed  $operation
     */
    #[Test]
    #[DataProvider('outOfTurn')]
    public function an_operation_out_of_turn_is_refused_and_changes_nothing(string $at, Closure $operation): void
    {
        $task = $this->task(stages: ['One']);
        $stage = $this->stage($task, 1);

        if ($at !== 'ready') {
            $stage = $this->claim('worker-1');
        }

        if ($at === 'review') {
            $this->tasks()->report($stage, 'Done.', null, $this->agent('worker-1'));
        }

        $before = $stage->refresh()->status;
        $entries = $task->journal()->count();

        $this->assertRefused('stage-not', fn () => $operation($this, $stage));
        $this->assertSame($before, $stage->refresh()->status);
        $this->assertSame($entries, $task->journal()->count());
    }

    #[Test]
    public function a_done_stage_cannot_be_cancelled(): void
    {
        $task = $this->task(stages: ['One', 'Two']);
        $stage = $this->claim();
        $this->tasks()->report($stage, 'Done.', null, $this->agent());
        $this->tasks()->accept($stage, $this->person());

        $this->assertRefused('stage-transition', fn () => $this->tasks()->cancelStage($this->stage($task, 1), $this->person()));
    }

    #[Test]
    public function cancelling_a_task_cancels_what_is_not_done(): void
    {
        $task = $this->task(stages: ['One', 'Two']);
        $stage = $this->claim();
        $this->tasks()->report($stage, 'Done.', null, $this->agent());
        $this->tasks()->accept($stage, $this->person());

        $task = $this->tasks()->cancel($task, $this->person(), 'Not needed any more.');

        $this->assertSame(TaskStatus::Cancelled, $task->status);
        $this->assertNotNull($task->closed_at);
        $this->assertSame(['done', 'cancelled'], $task->stages->map(static fn (Stage $stage): string => $stage->status->value)->all());
        $this->assertRefused('task-closed', fn () => $this->tasks()->cancel($task, $this->person()));
        $this->assertNull($this->claim());
    }

    #[Test]
    public function a_cancelled_stage_lets_the_next_one_start(): void
    {
        $task = $this->task(stages: ['One', 'Two']);

        $this->tasks()->cancelStage($this->stage($task, 1), $this->person());

        $this->assertSame(StageStatus::Ready, $this->stage($task, 2)->status);
        $this->assertRefused('last-stage', fn () => $this->tasks()->cancelStage($this->stage($task, 2), $this->person()));
    }

    #[Test]
    public function the_context_package_has_everything_a_fresh_session_needs(): void
    {
        $task = $this->task(stages: ['One', 'Two']);
        $one = $this->claim('worker-1');
        $this->tasks()->report($one, 'Schema is in.', 'abc123', $this->agent('worker-1'));
        $this->tasks()->accept($one, $this->agent('dispatcher'));

        $package = $this->dispatcher()->claimNext($this->agent('worker-2'));

        $this->assertSame([
            'slug' => 'shop',
            'title' => 'Shop',
            'workdir' => '/work/shop',
            'repo_url' => 'https://git.example.test/shop.git',
            'default_branch' => 'main',
            'rules' => 'Run the gate before reporting.',
        ], $package['project'] ?? null);
        $this->assertSame(['id' => $task->id, 'number' => 1, 'title' => 'Cart page', 'priority' => 'normal', 'branch' => 'feat/cart', 'spec' => 'The **spec**.', 'acceptance' => 'It works.'], $package['task'] ?? null);
        $this->assertSame(2, $package['stage']['position'] ?? null);
        $this->assertSame(2, $package['stage']['of'] ?? null);
        $this->assertSame('Do Two.', $package['stage']['brief'] ?? null);
        $this->assertSame('worker-2', $package['stage']['assignee'] ?? null);
        $this->assertNull($package['stage']['rework_remarks'] ?? null);
        $this->assertSame([['position' => 1, 'title' => 'One', 'report' => 'Schema is in.', 'result_ref' => 'abc123']], $package['accepted'] ?? null);
    }

    #[Test]
    public function a_task_without_a_branch_works_in_the_projects_default(): void
    {
        $this->task(stages: ['One'], values: ['branch' => null]);

        $this->assertSame('main', $this->dispatcher()->claimNext($this->agent())['task']['branch'] ?? null);
    }

    #[Test]
    public function an_archived_project_takes_no_new_task(): void
    {
        $project = $this->project();
        $this->tasks()->updateProject($project, ['archived' => true]);

        $this->assertRefused('project-archived', fn () => $this->task($project, ready: false));
    }

    #[Test]
    public function an_agent_has_a_name(): void
    {
        $this->assertRefused('agent-name', fn () => $this->agent('  '));
    }
}
