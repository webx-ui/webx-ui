<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Contracts;

use WebxUi\Inbox\Models\Submission;

/**
 * Something a site does with a stored submission, named in `webx-inbox.handlers` (§2.19).
 *
 * Each one runs as a job of its own, built by the container, so it may ask for whatever it
 * needs in its constructor. Throwing is how it says it failed: the exception becomes a
 * `handler_error` line in the submission's log, and the other handlers run all the same.
 * Returning is success, logged as `handled`.
 */
interface SubmissionHandler
{
    public function handle(Submission $submission): void;
}
