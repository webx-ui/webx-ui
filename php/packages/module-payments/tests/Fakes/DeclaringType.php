<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Fakes;

use WebxUi\Payments\Contracts\DeclaresGroup;
use WebxUi\Payments\Types\StoreType;

/** An offline type that does not stand where its kind would put it (groups spec §2.3). */
final class DeclaringType extends StoreType implements DeclaresGroup
{
    public const string KEY = 'store.declaring';

    public function __construct(private readonly string $group) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function methodKey(): string
    {
        return 'declaring';
    }

    public function awaitsPayment(): bool
    {
        return false;
    }

    public function settings(): array
    {
        return [];
    }

    public function defaultGroup(): string
    {
        return $this->group;
    }

    protected function words(): string
    {
        return 'webx-payments::methods.on_receipt';
    }
}
