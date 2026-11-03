<?php

declare(strict_types=1);

namespace WebxUi\Payments\Contracts;

use WebxUi\Payments\Credentials;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Method;
use WebxUi\Payments\Models\Payment;

/** The gateway can close a payment nobody has paid yet (spec §6.4, §9.4). */
interface CancelsPayments extends ChargesOnline
{
    /**
     * Closes a pending payment so it cannot be paid any more. False — the gateway refused (it is
     * paid already, or past closing).
     *
     * @throws GatewayUnavailable
     */
    public function cancel(Method $method, Credentials $credentials, Payment $payment, GatewayHttp $http): bool;
}
