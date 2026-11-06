<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Fakes;

use WebxUi\Payments\Availability;
use WebxUi\Payments\Contracts\MethodType;
use WebxUi\Payments\Contracts\ProvidesPaymentMarks;
use WebxUi\Payments\Kind;
use WebxUi\Payments\Method;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\PaymentSnapshot;
use WebxUi\Payments\Presentation;

/** A type that shows what the method's setting `marks` says, so a test picks the marks by row. */
final class MarkedType implements MethodType, ProvidesPaymentMarks
{
    public const string KEY = 'marked.card';

    public function marks(Method $method): array
    {
        return array_values((array) ($method->settings['marks'] ?? []));
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function provider(): string
    {
        return 'marked';
    }

    public function kind(): Kind
    {
        return Kind::Offline;
    }

    public function awaitsPayment(): bool
    {
        return false;
    }

    public function settings(): array
    {
        return [];
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
        return new Presentation($method->key, null);
    }

    public function snapshot(Method $method, PaymentContext $context): PaymentSnapshot
    {
        return new PaymentSnapshot($method->key, self::KEY, Kind::Offline, ['en' => $method->key]);
    }
}
