<?php

declare(strict_types=1);

namespace WebxUi\Payments\Testing;

use WebxUi\Payments\Contracts\DeclaresNotCardFields;

/**
 * «Gateway A» with the optional {@see DeclaresNotCardFields}: registered instead of
 * {@see FakeGateway} by a test that wants the contract on, since an interface is a property of the
 * class (spec §21.1).
 */
final readonly class GatewayANotCards extends FakeGateway implements DeclaresNotCardFields
{
    public const array FIELDS = ['create_date', 'payment_id'];

    public function notCardFields(): array
    {
        return self::FIELDS;
    }
}
