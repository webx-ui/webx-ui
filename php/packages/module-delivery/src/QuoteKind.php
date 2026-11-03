<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

enum QuoteKind: string
{
    case Fixed = 'fixed';
    case Free = 'free';
    case CarrierRates = 'carrier_rates';
    case Unavailable = 'unavailable';
}
