<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

/** Who pays the carrier — what a waybill says (§9.3). */
enum Payer: string
{
    case Buyer = 'buyer';
    case Store = 'store';
}
