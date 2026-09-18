<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Exceptions;

/**
 * There is no status at all to give an arriving submission.
 *
 * Only reachable by emptying the table by hand: the migration seeds five. Said out loud rather
 * than made up for — a submission invented into a status that does not exist would be one the
 * list never shows.
 */
final class NoStatuses extends InboxException
{
    public function __construct()
    {
        parent::__construct((string) trans('webx-inbox::errors.no-statuses'));
    }
}
