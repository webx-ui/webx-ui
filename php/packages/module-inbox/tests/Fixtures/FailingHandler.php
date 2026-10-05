<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests\Fixtures;

use RuntimeException;
use WebxUi\Inbox\Contracts\SubmissionHandler;
use WebxUi\Inbox\Models\Submission;

/** A CRM that is down. */
final class FailingHandler implements SubmissionHandler
{
    public function handle(Submission $submission): void
    {
        throw new RuntimeException('The CRM answered 503.');
    }
}
