<?php

declare(strict_types=1);

namespace WebxUi\Payments;

/** The status of an attempt (spec §8.1). */
enum PaymentStatus: string
{
    case Created = 'created';
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
