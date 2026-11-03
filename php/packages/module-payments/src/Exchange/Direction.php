<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

/** Which way an event of the exchange went (spec §20.1). */
enum Direction: string
{
    /** We asked the gateway: every request of a satellite, through GatewayHttp. */
    case Out = 'out';

    /** The gateway called us: a server-to-server notification. */
    case In = 'in';

    /** The buyer's browser carried it: the handoff to the gateway's page and the way back. */
    case Browser = 'browser';

    /** The base decided by itself: an attempt expired, a manager marked a payment. No bodies. */
    case Internal = 'internal';
}
