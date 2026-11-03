<?php

declare(strict_types=1);

namespace WebxUi\Payments;

/** How a method is paid (spec §6.1): the manager marks it, or the gateway confirms it. */
enum Kind: string
{
    case Offline = 'offline';
    case Online = 'online';
}
