<?php

declare(strict_types=1);

namespace WebxUi\Payments\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Handoff;
use WebxUi\Payments\InvalidNotification;
use WebxUi\Payments\Method;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Notification;
use WebxUi\Payments\StartNeeds;
use WebxUi\Payments\StartRequest;

/**
 * An online payment through the gateway's page (spec §6.3): the minimum every gateway of the
 * review has — its page, our number of the payment, a status by API, a server notification and an
 * address to come back to. What only some gateways can do comes as interfaces on top of this one
 * (§6.4): `CancelsPayments`, `CompletesApproval`, `CapturesPayments`.
 *
 * Every call to the gateway goes through the `GatewayHttp` the base passes in — the base journals
 * it (§20.3). A type never reads `payment_methods` or the environment: its keys are `Credentials`.
 */
interface ChargesOnline extends MethodType
{
    /**
     * Opens a payment at the gateway for this attempt and tells where the buyer goes. Called on
     * GET /pay/go/{uuid}, once per attempt, never at the order's commit (§8.2).
     *
     * @throws GatewayUnavailable
     */
    public function start(Method $method, Credentials $credentials, StartRequest $request, GatewayHttp $http): Handoff;

    /**
     * Asks the gateway's API what happened to the payment. Reads only: never captures or charges.
     *
     * @throws GatewayUnavailable
     */
    public function fetchStatus(Method $method, Credentials $credentials, Payment $payment, GatewayHttp $http): GatewayStatus;

    /**
     * What the type needs of the buyer and the order for start(): the base fills only these
     * (decisions 42, 43).
     */
    public function needs(): StartNeeds;

    /**
     * False — the gateway's notifications are not signed, or the signature does not cover the data
     * (decision 33): a notification is only a reason to ask fetchStatus(); its status is never applied.
     */
    public function trustsNotifications(): bool;

    /**
     * Reads a server-to-server notification the base has journaled already (§9.2): checks the
     * signature with the credentials — in constant time —, finds our attempt and the gateway's
     * status. A type that does not trust its notifications answers Notification::poke(); one about
     * something else — ::unrelated().
     *
     * @throws InvalidNotification a bad signature, a malformed body
     */
    public function readNotification(Method $method, Credentials $credentials, Request $request, GatewayHttp $http): Notification;

    /** The answer the gateway expects for an accepted notification ('OK', a signed JSON, an empty 200). */
    public function acceptResponse(Method $method, Credentials $credentials, Notification $notification): Response;

    /** The answer for a rejected one; most gateways retry on non-2xx. */
    public function rejectResponse(Method $method, Credentials $credentials, InvalidNotification $e): Response;
}
