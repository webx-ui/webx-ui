<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;
use Throwable;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Models\Task;
use WebxUi\Tasks\Workflow\Actor;
use WebxUi\Tasks\Workflow\Journal;
use WebxUi\Tasks\Workflow\JournalKind;

/**
 * One notice on its way (§6a, delivery): queued after the move is committed, so the move never
 * waits for Telegram and never comes undone because of it.
 *
 * Three attempts, a pause between them; one on a `sync` queue. The failure is caught here rather than thrown: on a
 * `sync` queue a thrown job is an error in the request that made the move — the move is saved,
 * and the person who made it would be told it failed. After the last attempt the failure goes to
 * the log and to the task's journal, as the system's comment.
 */
final class SendNotice implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const int ATTEMPTS = 3;

    /** Seconds before the second and the third attempt. */
    public const array PAUSES = [30, 120];

    /** The job counts its own attempts; the worker is not to give up on it first. */
    public int $tries = self::ATTEMPTS;

    /**
     * @param  array{event: string, task_id: int, stage_id: int|null, text: string}  $notice
     */
    public function __construct(public readonly array $notice) {}

    public function handle(Notifier $notifier, Journal $journal): void
    {
        $notice = Notice::fromArray($this->notice);

        try {
            $notifier->send($notice);
        } catch (Throwable $exception) {
            // A `sync` queue cannot come back later — a pause would hold up the request that made
            // the move — so there the first failure is the last.
            $attempt = $this->job === null || $this->job instanceof SyncJob ? self::ATTEMPTS : $this->attempts();

            if ($attempt < self::ATTEMPTS) {
                $this->release(self::PAUSES[$attempt - 1] ?? self::PAUSES[array_key_last(self::PAUSES)]);

                return;
            }

            $this->giveUp($notice, $exception, $journal);
        }
    }

    private function giveUp(Notice $notice, Throwable $exception, Journal $journal): void
    {
        $reason = $exception instanceof NoticeFailed ? $exception->getMessage() : 'The notice could not be sent: '.$exception::class;

        Log::warning("Tasks: the notice «{$notice->event}» for task {$notice->taskId} was not delivered. {$reason}");

        $task = Task::withTrashed()->find($notice->taskId);

        if ($task instanceof Task) {
            $stage = $notice->stageId === null ? null : Stage::query()->find($notice->stageId);
            $journal->write($task, $stage, Actor::system(), JournalKind::Comment, $reason, ['notice' => $notice->event]);
        }
    }
}
