<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Notifications;

use WebxUi\Tasks\Events\StageMoved;
use WebxUi\Tasks\Events\TaskStatusChanged;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Models\Task;
use WebxUi\Tasks\Workflow\ReviewMode;
use WebxUi\Tasks\Workflow\StageStatus;
use WebxUi\Tasks\Workflow\TaskStatus;

/**
 * Which moves a person hears about (§6a): a stage waiting for a review, an agent's question, a
 * stage sent back, a task done — each when its switch is on and there is somewhere to send to.
 *
 * The notice is queued `afterCommit`. The events fire inside the move's transaction, and a move
 * that is rolled back — a refusal halfway through, a dry run — has nothing to announce.
 */
final class NotifyPeople
{
    public function __construct(
        private readonly NotificationSettings $settings,
        private readonly Notifier $notifier,
        private readonly Notices $notices,
    ) {}

    public function stageMoved(StageMoved $moved): void
    {
        $event = match ($moved->to) {
            StageStatus::Review => null,
            StageStatus::Blocked => 'stage.blocked',
            StageStatus::Rework => 'stage.rework',
            default => false,
        };

        if ($event === false) {
            return;
        }

        $stage = Stage::query()->find($moved->stageId);

        if (! $stage instanceof Stage) {
            return;
        }

        $event ??= $stage->review_mode === ReviewMode::Human ? 'stage.review_human' : 'stage.review_auto';

        if ($this->wanted($event)) {
            $this->queue($this->notices->forStage($event, $stage, $moved->body));
        }
    }

    public function taskChanged(TaskStatusChanged $changed): void
    {
        if ($changed->to !== TaskStatus::Done || ! $this->wanted('task.done')) {
            return;
        }

        $task = Task::query()->find($changed->taskId);

        if ($task instanceof Task) {
            $this->queue($this->notices->forTask('task.done', $task));
        }
    }

    private function wanted(string $event): bool
    {
        return $this->settings->enabled($event) && $this->notifier->configured();
    }

    private function queue(Notice $notice): void
    {
        SendNotice::dispatch($notice->toArray())->afterCommit();
    }
}
