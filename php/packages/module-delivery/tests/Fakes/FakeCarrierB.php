<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Fakes;

use WebxUi\Delivery\ConnectionResult;
use WebxUi\Delivery\Contracts\Carrier;

/** «Carrier B»: a satellite whose sender is typed by hand — no options from its account. */
final class FakeCarrierB implements Carrier
{
    public function key(): string
    {
        return 'carrier-b';
    }

    public function title(string $locale): string
    {
        return 'Carrier B';
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
        return [['name' => 'sender', 'type' => 'text', 'label' => ['en' => 'Sender']]];
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
