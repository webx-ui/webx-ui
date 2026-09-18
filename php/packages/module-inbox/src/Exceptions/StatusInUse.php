<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Exceptions;

/**
 * A status still worn by submissions cannot be deleted — they would have nothing to be in.
 */
final class StatusInUse extends InboxException
{
    public function __construct(string $key)
    {
        parent::__construct((string) trans('webx-inbox::errors.status-in-use', ['status' => $key]));
    }
}
