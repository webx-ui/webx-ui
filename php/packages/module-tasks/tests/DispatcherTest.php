<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Tasks\Models\JournalEntry;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Workflow\JournalKind;
use WebxUi\Tasks\Workflow\StageStatus;

/**
 * Handing out work: one stage to one agent, in the order §5 gives.
 */
final class DispatcherTest extends TestCase
{
    #[Test]
    public function two_agents_racing_for_one_stage_get_one_stage_and_one_empty_answer(): void
    {
        $task = $this->task(stages: ['One']);

        // Both read the queue before either writes: the race the conditional update is for.
        $seenByFirst = $this->dispatcher()->candidates()->first();
        $seenBySecond = $this->dispatcher()->candidates()->first();
        $this->assertNotNull($seenByFirst);
        $this->assertNotNull($seenBySecond);

        $this->assertTrue($this->dispatcher()->take($seenByFirst, $this->agent('worker-1')));
        $this->assertFalse($this->dispatcher()->take($seenBySecond, $this->agent('worker-2')));

        $stage = $this->stage($task, 1);
        $this->assertSame(StageStatus::InProgress, $stage->status);
        $this->assertSame('worker-1', $stage->assignee);
        $this->assertNull($this->dispatcher()->claimNext($this->agent('worker-2')));

        // The loser left no trace: one line for the one stage taken.
        $this->assertSame(1, JournalEntry::query()->where('stage_id', $stage->id)->where('meta->to', 'in_progress')->count());
    }

    #[Test]
    public function a_lost_race_moves_on_to_the_next_candidate(): void
    {
        $project = $this->project();
        $this->task($project, ['A']);
        $this->task($project, ['B']);

        $stale = $this->dispatcher()->candidates()->first();
        $this->assertNotNull($stale);
        $this->dispatcher()->take($stale, $this->agent('worker-1'));

        $this->assertSame('B', $this->claim('worker-2')?->title);
    }

    #[Test]
    public function rework_comes_first_then_priority_then_age(): void
    {
        $project = $this->project();

        Carbon::setTestNow('2026-10-01 10:00');
        $oldLow = $this->task($project, ['old low'], ['priority' => 'low']);
        Carbon::setTestNow('2026-10-02 10:00');
        $oldNormal = $this->task($project, ['old normal']);
        Carbon::setTestNow('2026-10-03 10:00');
        $this->task($project, ['new normal']);
        Carbon::setTestNow('2026-10-04 10:00');
        $this->task($project, ['new high'], ['priority' => 'high']);

        // A low-priority stage sent back beats everything that has not been started.
        $this->dispatcher()->take($this->stage($oldLow, 1), $this->agent('worker-0'));
        $this->tasks()->report($this->stage($oldLow, 1), 'Done.', null, $this->agent('worker-0'));
        $this->tasks()->rework($this->stage($oldLow, 1), 'Again.', $this->person());
        Carbon::setTestNow();

        $order = [];

        while (($stage = $this->claim('worker-'.count($order))) !== null) {
            $order[] = $stage->title;
        }

        $this->assertSame(['old low', 'new high', 'old normal', 'new normal'], $order);
        $this->assertSame(StageStatus::InProgress, $this->stage($oldNormal, 1)->status);
    }

    #[Test]
    public function nothing_is_handed_out_from_a_draft_a_cancelled_task_or_an_archived_project(): void
    {
        $project = $this->project();
        $this->task($project, ['draft'], ready: false);
        $this->tasks()->cancel($this->task($project, ['cancelled']), $this->person());

        $archived = $this->project('old');
        $this->task($archived, ['archived']);
        $this->tasks()->updateProject($archived, ['archived' => true]);

        $deleted = $this->task($project, ['deleted']);
        $deleted->delete();

        $this->assertNull($this->dispatcher()->claimNext($this->agent()));
    }

    #[Test]
    public function an_agent_can_be_kept_to_one_project(): void
    {
        $this->task($this->project('shop'), ['shop work']);
        $blog = $this->project('blog');
        $this->task($blog, ['blog work']);

        $this->assertSame('blog work', $this->claim('worker-1', $blog)?->title);
        $this->assertNull($this->claim('worker-2', $blog));
    }

    #[Test]
    public function taking_a_stage_is_journalled_under_the_agents_name(): void
    {
        $this->task(stages: ['One']);

        $stage = $this->claim('session-42');

        $this->assertInstanceOf(Stage::class, $stage);
        $this->assertNotNull($stage->claimed_at);

        $entry = JournalEntry::query()->where('stage_id', $stage->id)->latest('id')->firstOrFail();
        $this->assertSame(JournalKind::Status, $entry->kind);
        $this->assertSame('session-42', $entry->actor_name);
        $this->assertSame('agent', $entry->actor_type->value);
        $this->assertSame(['from' => 'ready', 'to' => 'in_progress', 'attempt' => 1], $entry->meta);
    }
}
