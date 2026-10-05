<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Queue\SerializesModels;
use WebxUi\Inbox\Models\Submission;

/**
 * A submission is in the database with all of its answers and files (§2.19).
 *
 * The seam for what a site does next — a CRM, a mailing list, a letter back to the visitor —
 * without a fork of the intake. Not an Eloquent event: the row is written before its values,
 * so `created` would hand a listener a submission with no answers in it.
 *
 * Fired for every source, the site's form and one typed in by hand alike (`$submission->source`
 * tells them apart), and never for what the antispam trapped or refused — those are not written.
 * After the commit, so a queued listener never wakes up to a row that was rolled back.
 *
 * `$repeated` is the same submission sent again inside the duplicate window (§2.7): a double
 * click, a page reloaded on a POST. Its answers were written again; whether that is news is the
 * listener's call. The handlers of `webx-inbox.handlers` run once, on the first.
 */
class SubmissionStored implements ShouldDispatchAfterCommit
{
    use SerializesModels;

    public function __construct(
        public Submission $submission,
        public bool $repeated = false,
    ) {}
}
