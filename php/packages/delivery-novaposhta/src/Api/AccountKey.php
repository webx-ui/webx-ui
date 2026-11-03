<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Api;

use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;

/**
 * The account's API key for the work the site does on its own — the directories (§10.3): the one
 * stored by the panel in `delivery_carriers.credentials`, read on every call so that a key replaced
 * in the panel is the next page's key.
 *
 * `$override` is for a one-off process on a development copy that must not store a client's key
 * in its database (decision 19): the process binds its own instance; the site never does.
 */
final readonly class AccountKey
{
    public function __construct(private ?string $override = null) {}

    public function get(): ?string
    {
        if ($this->override !== null) {
            return trim($this->override) === '' ? null : trim($this->override);
        }

        $credentials = DeliveryCarrier::query()->find(NovaPoshtaCarrier::KEY)?->credentials;
        $key = is_array($credentials) && is_string($credentials['api_key'] ?? null) ? trim($credentials['api_key']) : '';

        return $key === '' ? null : $key;
    }
}
