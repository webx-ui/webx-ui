<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

/** The benefit keys delivery understands (decisions 2 and 37). */
final class Benefits
{
    /** The shop pays the carrier: «Free delivery», and the sender pays on the waybill (§9.4). */
    public const string PAID_BY_STORE = 'shipping.paid_by_store';
}
