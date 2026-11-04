<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use Illuminate\Contracts\Config\Repository;
use WebxUi\Payments\Contracts\ChargesOnline;
use WebxUi\Payments\Contracts\DeclaresNotCardFields;
use WebxUi\Payments\Contracts\Gateway;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Exchange\ExchangeSecrets;
use WebxUi\Payments\Exchange\InProcessHosts;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\Method;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\UnknownGateway;

/**
 * What the base hands a satellite for one call (spec §20.3): its type, its gateway, the method's
 * keys and a `GatewayHttp` bound to the gateway, the method, the attempt and the trace — with the
 * secrets the journal wipes: the keys, the gateway's secret headers and fields — and the fields it
 * said are not card numbers.
 */
final readonly class GatewayCalls
{
    public function __construct(
        private MethodTypes $types,
        private Gateways $gateways,
        private ExchangeJournal $journal,
        private Repository $config,
        private InProcessHosts $hosts,
    ) {}

    /** The online type of a method; null — the type is not installed or is not online. */
    public function type(string $typeKey): ?ChargesOnline
    {
        if (! $this->types->has($typeKey)) {
            return null;
        }

        $type = $this->types->get($typeKey);

        return $type instanceof ChargesOnline ? $type : null;
    }

    /**
     * The gateway of an online type: a type without one is a satellite's mistake (§6.2).
     *
     * @throws UnknownGateway
     */
    public function gateway(ChargesOnline $type): Gateway
    {
        return $this->gateways->get($type->provider());
    }

    public function http(
        ChargesOnline $type,
        Method $method,
        Credentials $credentials,
        ExchangeKind $kind,
        string $source,
        ?Payment $payment = null,
        ?string $trace = null,
    ): GatewayHttp {
        $gateway = $this->gateway($type);

        return new GatewayHttp(
            $this->journal,
            $this->config,
            new ExchangeContext(
                gateway: $gateway->key(),
                methodKey: $method->key,
                paymentId: $payment?->exists === true ? $payment->id : null,
                orderId: $payment?->exists === true ? $payment->order_id : null,
                source: $source,
                trace: $trace,
            ),
            new ExchangeSecrets(
                $credentials,
                $gateway->secretHeaders(),
                $gateway->secretFields(),
                $gateway instanceof DeclaresNotCardFields ? $gateway->notCardFields() : [],
            ),
            $kind,
            $this->hosts,
        );
    }
}
