<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use WebxUi\Cache\CacheGroups;
use WebxUi\Payments\Models\PaymentMethod;

/**
 * The methods a buyer may pay with (spec §7.1): the enabled methods of known types, by position,
 * read from the cache of the group `payments` (§18) and filtered for one context — the amount, the
 * currency, the chosen delivery, the type's own limits and, for an online method, its keys.
 *
 * Nothing here calls a gateway: a list costs no query on a warm cache.
 */
final readonly class Methods
{
    /** The cache group of the enabled methods, bumped by any save of a method. */
    public const string GROUP = 'payments';

    public function __construct(
        private CacheGroups $groups,
        private MethodTypes $types,
        private Gateways $gateways,
        private MethodCredentials $credentials,
    ) {}

    /**
     * The enabled methods by position, known types or not.
     *
     * @return list<Method>
     */
    public function enabled(): array
    {
        // Every language of a method is in its row: one entry for the whole site.
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->groups->remember('payments.methods', [self::GROUP], static fn (): array => PaymentMethod::query()
            ->where('enabled', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(static fn (PaymentMethod $row): array => Method::row($row))
            ->all(), localized: false);

        return array_map(Method::fromArray(...), $rows);
    }

    /** An enabled method by its key; null — none, or switched off. */
    public function find(string $key): ?Method
    {
        foreach ($this->enabled() as $method) {
            if ($method->key === $key) {
                return $method;
            }
        }

        return null;
    }

    /**
     * A method by its key, switched on or off, read from its row: an attempt opened with a method
     * the shop switched off later still hears from its gateway (§9.2). Null — no such method.
     */
    public function any(string $key): ?Method
    {
        $row = PaymentMethod::query()->where('key', $key)->first();

        return $row !== null ? Method::of($row) : null;
    }

    /**
     * What the buyer is offered for this context, in the shop's order.
     *
     * @return list<MethodOption>
     */
    public function available(PaymentContext $context): array
    {
        $options = [];

        foreach ($this->enabled() as $method) {
            if ($this->check($method, $context)->available) {
                $options[] = $this->option($method, $context);
            }
        }

        return $options;
    }

    /**
     * Every enabled method with whether it is offered and why not — for the manager and the
     * agent (§7.2); the buyer never sees an unavailable method.
     *
     * @return array<string, Availability> by method key
     */
    public function explain(PaymentContext $context): array
    {
        $answers = [];

        foreach ($this->enabled() as $method) {
            $answers[$method->key] = $this->check($method, $context);
        }

        return $answers;
    }

    /**
     * The enabled methods that wait for the delivery: hidden while the buyer has not chosen one,
     * so the step can say «Choose delivery first» (§7.3).
     */
    public function waitingForDelivery(PaymentContext $context): bool
    {
        if (! $context->shippingPending) {
            return false;
        }

        foreach ($this->enabled() as $method) {
            if ($method->rule()->dependsOnDelivery() && $this->types->has($method->type)) {
                return true;
            }
        }

        return false;
    }

    /** Whether the method may be offered in this context, in the order of §7.1. */
    public function check(Method $method, PaymentContext $context): Availability
    {
        if ($context->payable <= 0) {
            return Availability::no('nothing_to_pay');
        }

        if (! $this->types->has($method->type)) {
            return Availability::no('type_missing', ['type' => $method->type]);
        }

        $type = $this->types->get($method->type);
        $currencies = $type->currencies();

        if ($currencies !== null && ! in_array(strtoupper($context->currency), array_map('strtoupper', $currencies), true)) {
            return Availability::no('currency', ['currency' => $context->currency]);
        }

        if ($method->minAmount !== null && $context->payable < $method->minAmount) {
            return Availability::no('amount_below_min');
        }

        if ($method->maxAmount !== null && $context->payable > $method->maxAmount) {
            return Availability::no('amount_above_max');
        }

        $rule = $method->rule();

        if ($context->shippingPending && $rule->dependsOnDelivery()) {
            return Availability::no('delivery_pending');
        }

        if (! $rule->allows($context->shipping)) {
            return Availability::no('delivery_rule');
        }

        $own = $type->available($method, $context);

        if (! $own->available) {
            return $own;
        }

        if ($type->kind() === Kind::Online && ! $this->gateways->has($type->provider())) {
            return Availability::no('gateway_missing', ['gateway' => $type->provider()]);
        }

        // The keys are in the column or in the site's environment (§15.1).
        if ($type->kind() === Kind::Online && ! $method->hasCredentials && ! $this->credentials->inEnvironment($method)) {
            return Availability::no('no_credentials');
        }

        return Availability::yes();
    }

    public function option(Method $method, PaymentContext $context): MethodOption
    {
        $type = $this->types->get($method->type);
        $presented = $type->present($method, $context);

        return new MethodOption($method, $type, $presented->title, $presented->note, $method->icon, $type->kind());
    }
}
