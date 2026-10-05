<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Submissions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;
use Throwable;
use WebxUi\Inbox\Contracts\SubmissionHandler;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;

/**
 * One configured handler, run against one submission (§2.19).
 *
 * A job per handler, so a CRM that hangs does not hold up the letter to the visitor, and one
 * that throws does not stop the next. It never rethrows: on the `sync` queue the job runs inside
 * the visitor's request, and the submission is already saved — a failure is a line in its log,
 * not a 500. For the same reason it is tried once; a handler that wants retries queues a job of
 * its own with them.
 */
final class RunHandler implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /** A submission pruned before the queue reached it has nobody left to tell. */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  class-string<SubmissionHandler>  $handler
     */
    public function __construct(
        public Submission $submission,
        public string $handler,
    ) {}

    public function handle(Container $container, LoggerInterface $log): void
    {
        try {
            /** @var SubmissionHandler $handler */
            $handler = $container->make($this->handler);
            $handler->handle($this->submission);
        } catch (Throwable $exception) {
            $log->error('webx-inbox: handler {handler} failed on submission {id}.', [
                'handler' => $this->handler,
                'id' => $this->submission->getKey(),
                'error' => $exception->getMessage(),
            ]);

            $this->submission->log(
                SubmissionEvent::HANDLER_ERROR,
                $this->handler,
                mb_substr($exception->getMessage() !== '' ? $exception->getMessage() : $exception::class, 0, 255),
            );

            return;
        }

        $this->submission->log(SubmissionEvent::HANDLED, $this->handler);
    }
}
