<?php

declare(strict_types=1);

namespace WebxUi\Payments\Contracts;

use WebxUi\Payments\Credentials;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Method;
use WebxUi\Payments\Models\Payment;

/**
 * The gateway approved, the shop must still confirm on its server — approve is not paid (spec
 * §6.4). Called by the base on return and from the poll before fetchStatus(), under the attempt's
 * lock; idempotent: a second call for the same attempt does not charge twice (the attempt's uuid is
 * the key).
 */
interface CompletesApproval extends ChargesOnline
{
    /** @throws GatewayUnavailable */
    public function complete(Method $method, Credentials $credentials, Payment $payment, GatewayHttp $http): GatewayStatus;
}
