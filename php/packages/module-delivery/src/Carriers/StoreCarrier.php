<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Carriers;

use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\ConnectionResult;
use WebxUi\Delivery\Contracts\Carrier;

/** The shop itself: its own points and its own courier, no API and nothing to sync. */
final class StoreCarrier implements Carrier
{
    public function key(): string
    {
        return Carriers::STORE;
    }

    public function title(string $locale): string
    {
        return (string) trans('webx-delivery::delivery.carriers.store', [], $locale);
    }

    public function icon(): ?string
    {
        return null;
    }

    public function countries(): array
    {
        return [];
    }

    public function settings(): array
    {
        return [];
    }

    public function credentials(): array
    {
        return [];
    }

    public function testConnection(array $credentials): ConnectionResult
    {
        return ConnectionResult::ok();
    }
}
