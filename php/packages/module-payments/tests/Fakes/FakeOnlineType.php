<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Fakes;

use WebxUi\Payments\Availability;
use WebxUi\Payments\Contracts\MethodType;
use WebxUi\Payments\Kind;
use WebxUi\Payments\Method;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\PaymentSnapshot;
use WebxUi\Payments\Presentation;

/** «Gateway A»'s card as far as the list of methods needs it: online, euros only. */
final class FakeOnlineType implements MethodType
{
    public function key(): string
    {
        return 'gateway-a.card';
    }

    public function provider(): string
    {
        return 'gateway-a';
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
        return [];
    }

    public function currencies(): array
    {
        return ['EUR'];
    }

    public function available(Method $method, PaymentContext $context): Availability
    {
        return Availability::yes();
    }

    public function present(Method $method, PaymentContext $context): Presentation
    {
        return new Presentation((string) $method->title($context->locale));
    }

    public function snapshot(Method $method, PaymentContext $context): PaymentSnapshot
    {
        return new PaymentSnapshot($method->key, $this->key(), Kind::Online, $method->titles);
    }
}
