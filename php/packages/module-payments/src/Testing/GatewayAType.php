<?php

declare(strict_types=1);

namespace WebxUi\Payments\Testing;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use Throwable;
use WebxUi\Payments\Availability;
use WebxUi\Payments\Contracts\ChargesOnline;
use WebxUi\Payments\GatewayAmount;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Kind;
use WebxUi\Payments\Method;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\PaymentSnapshot;
use WebxUi\Payments\Presentation;
use WebxUi\Payments\Reported;
use WebxUi\Payments\Words;

/**
 * What the three types of gateway A share (spec §21.1): online, any currency, the shop's title or
 * the gateway's, and the translation of the gateway's statuses into `Reported` by each type's own
 * table — an unknown status is Pending with a reason, never a guess.
 */
abstract class GatewayAType implements ChargesOnline
{
    public function __construct(protected readonly FakeGatewayServer $server) {}

    /** 'card' | 'form' | 'unsigned' — the part after the provider. */
    abstract protected function kindKey(): string;

    /** The gateway's word for this type's page. */
    abstract protected function defaultTitle(): string;

    /**
     * The gateway's statuses and what they are (the table a satellite keeps, §3.3 of the review).
     *
     * @return array<string, Reported>
     */
    abstract public function statuses(): array;

    /** A payment the gateway has no record of: lost, or never begun by the buyer (§21.2). */
    protected static function unknownPayment(mixed $error, ?string $gatewayRef = null): GatewayStatus
    {
        return $error === FakeGatewayServer::NOT_STARTED
            ? new GatewayStatus(Reported::Pending, $gatewayRef, reason: 'The buyer did not begin the payment', notStarted: true)
            : new GatewayStatus(Reported::Pending, $gatewayRef, reason: 'The gateway does not know the payment');
    }

    public function key(): string
    {
        return FakeGateway::KEY.'.'.$this->kindKey();
    }

    public function provider(): string
    {
        return FakeGateway::KEY;
    }

    public function kind(): Kind
    {
        return Kind::Online;
    }

    public function awaitsPayment(): bool
    {
        return true;
    }

    public function settings(): array
    {
        return [
            ['name' => 'expires_minutes', 'type' => 'number', 'label' => 'Minutes the payment page works', 'default' => null],
        ];
    }

    public function currencies(): ?array
    {
        return null;
    }

    public function available(Method $method, PaymentContext $context): Availability
    {
        return Availability::yes();
    }

    public function present(Method $method, PaymentContext $context): Presentation
    {
        return new Presentation($method->title($context->locale) ?? $this->defaultTitle(), $method->description($context->locale));
    }

    public function snapshot(Method $method, PaymentContext $context): PaymentSnapshot
    {
        $titles = Words::clean($method->titles);

        return new PaymentSnapshot($method->key, $this->key(), Kind::Online, $titles !== [] ? $titles : [$context->locale => $this->defaultTitle()]);
    }

    /** The gateway's status in the base's terms: unknown — Pending, with the gateway's word kept. */
    protected function reported(string $status): Reported
    {
        return $this->statuses()[$status] ?? Reported::Pending;
    }

    protected function unknown(string $status): ?string
    {
        return isset($this->statuses()[$status]) ? null : "Unknown status of the gateway: {$status}";
    }

    /** @throws GatewayUnavailable */
    protected static function amount(mixed $decimal, string $currency): ?int
    {
        if ($decimal === null) {
            return null;
        }

        try {
            return GatewayAmount::fromDecimal(is_string($decimal) || is_int($decimal) ? $decimal : '', $currency);
        } catch (InvalidArgumentException) {
            throw GatewayUnavailable::malformed('amount');
        }
    }

    protected static function time(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A request to the gateway: a timeout is GatewayUnavailable, the answer comes back as it is.
     *
     * @param  callable(): Response  $call
     *
     * @throws GatewayUnavailable
     */
    protected static function call(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException $e) {
            throw GatewayUnavailable::timeout($e);
        }
    }

    /** @return array<string, mixed> */
    protected static function json(Response $answer): array
    {
        $body = $answer->json();

        if (! is_array($body)) {
            throw GatewayUnavailable::malformed();
        }

        return $body;
    }
}
