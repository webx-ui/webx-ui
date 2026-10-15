<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Notifications;

/**
 * Where a notice goes (§6a). Telegram out of the box; a site binds its own channel in the
 * container — `$this->app->bind(Notifier::class, MyChannel::class)` — and every event of the queue
 * goes there instead.
 *
 * A notifier either delivers or throws: the job that calls it counts the attempts, and the last
 * failure is written to the task's journal.
 */
interface Notifier
{
    /** Whether there is anywhere to send to; nothing is queued while there is not. */
    public function configured(): bool;

    /**
     * @throws NoticeFailed
     */
    public function send(Notice $notice): void;
}
