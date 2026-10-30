<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * A message is not sent by hand, and nothing is written (decision 99): the notifications module is
 * not there, the order has no e-mail, nothing gives the event a reason or no channel of it writes to
 * the buyer, the row of the log is another record's — or the notifications module refused it, in its
 * own words (`refused`): a row it cannot send again, the same sent a moment ago.
 */
final class NotifyRefused extends Refusal
{
    public const string UNAVAILABLE = 'unavailable';

    public const string NO_EMAIL = 'no_email';

    public const string NO_BASIS = 'no_basis';

    public const string NO_CHANNEL = 'no_channel';

    public const string FOREIGN = 'foreign';

    public const string REFUSED = 'refused';

    /** The refusal in the panel's words; one of the notifications module comes in its own. */
    public function said(): string
    {
        return $this->reason === self::REFUSED
            ? $this->getMessage()
            : (string) __('webx-orders::panel.notify-refusals.'.$this->reason);
    }
}
