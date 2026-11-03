<?php

declare(strict_types=1);

namespace WebxUi\Payments\Contracts;

use WebxUi\Payments\Credentials;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\GatewayRefused;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Method;
use WebxUi\Payments\Models\Payment;

/** Two-stage payment (spec §6.4, §9.5, decision 32): hold, then capture all or a part, or let it go. */
interface CapturesPayments extends ChargesOnline
{
    /** Days the gateway keeps a hold when it does not say itself; the poll warns before (§9.3). */
    public function maxHoldDays(): int;

    /** Whether a capture may take less than was held; false — the base never asks for less (decision 52). */
    public function capturesPartially(): bool;

    /**
     * Takes $amount (≤ authorized) of a held payment: Paid on success, Pending if the gateway is
     * slow.
     *
     * @throws GatewayRefused more than is held, nothing held, a capture the gateway does not take
     * @throws GatewayUnavailable
     */
    public function capture(Method $method, Credentials $credentials, Payment $payment, int $amount, GatewayHttp $http): GatewayStatus;

    /**
     * Lets the hold go: Cancelled on success.
     *
     * @throws GatewayRefused
     * @throws GatewayUnavailable
     */
    public function void(Method $method, Credentials $credentials, Payment $payment, GatewayHttp $http): GatewayStatus;
}
