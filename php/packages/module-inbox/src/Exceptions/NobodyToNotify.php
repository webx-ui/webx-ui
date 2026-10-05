<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Exceptions;

/**
 * The notification was asked for again, and the form names nobody to write to — or nobody it
 * names is left: an address that is not one, an account deleted or switched off.
 *
 * Refused rather than answered "done": a resend that wrote to nobody and said nothing is the
 * silence this module exists to break.
 */
final class NobodyToNotify extends InboxException
{
    public function __construct(string $form)
    {
        parent::__construct((string) trans('webx-inbox::errors.nobody-to-notify', ['form' => $form]));
    }
}
