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

    public function __construct(string $reason, string $message = '', public readonly ?string $event = null)
    {
        parent::__construct($reason, $message);
    }

    /** The refusal in the panel's words; one of the notifications module comes in its own. */
    public function said(): string
    {
        return $this->reason === self::REFUSED ? $this->getMessage() : self::label($this->reason, $this->event);
    }

    /**
     * A reason in the panel's words. «Nothing happened» says little of a letter that asks for money:
     * for `orders.payment_requested` it is «the order owes nothing» (§40.2).
     */
    public static function label(string $reason, ?string $event = null): string
    {
        $key = $reason === self::NO_BASIS && $event === 'orders.payment_requested' ? 'nothing_due' : $reason;

        return (string) __('webx-orders::panel.notify-refusals.'.$key);
    }
}
