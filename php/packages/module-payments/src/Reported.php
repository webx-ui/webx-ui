<?php

declare(strict_types=1);

namespace WebxUi\Payments;

/**
 * What a gateway said of a payment, in the base's closed list (spec §6.3): the satellite maps its
 * gateway's statuses here. An unknown one is Pending with a reason; Paid only when the money was
 * taken — «completed» is not «paid».
 */
enum Reported: string
{
    case Pending = 'pending';
    /** Money held, waits for a capture (§9.5). */
    case Authorized = 'authorized';
    /** Money taken. */
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    /** Given back from the gateway's cabinet; refundedAmount says how much. */
    case Refunded = 'refunded';
}
