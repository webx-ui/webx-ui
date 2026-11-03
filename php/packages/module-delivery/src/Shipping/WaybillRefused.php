<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Shipping;

use Throwable;
use WebxUi\Delivery\DeliveryException;

/**
 * The carrier, or the base before it, said no to a waybill (§11.2): the message is its reason in
 * words, shown to the manager as it is.
 *
 * A refusal about one field of the dialog names it — `recipient_first_name`, `recipient_phone`,
 * `extra.weight` — so that the dialog marks that field rather than the whole waybill; without a
 * field the reason stands above the form.
 */
final class WaybillRefused extends DeliveryException
{
    public function __construct(string $message = '', public readonly ?string $field = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** A refusal the manager fixes in one field of the dialog. */
    public static function field(string $field, string $message): self
    {
        return new self($message, $field);
    }
}
