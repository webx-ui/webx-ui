<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Fakes;

use WebxUi\Delivery\ConnectionResult;
use WebxUi\Delivery\Contracts\Carrier;

/** «Carrier A» of these tests: a satellite's carrier with nothing behind it. */
final class FakeCarrierA implements Carrier
{
    public function key(): string
    {
        return 'carrier-a';
    }

    public function title(string $locale): string
    {
        return 'Carrier A';
    }

    public function icon(): ?string
    {
        return null;
    }

    public function countries(): array
    {
        return ['XX'];
    }

    public function settings(): array
    {
        return [];
    }

    public function credentials(): array
    {
        return [['name' => 'api_key']];
    }

    public function testConnection(array $credentials): ConnectionResult
    {
        return ConnectionResult::ok();
    }
}
