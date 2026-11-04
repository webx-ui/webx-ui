<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Fakes;

use WebxUi\Payments\Testing\GatewayACard;

/** Gateway A's card, refusing in a key of a dictionary, the way a satellite does. */
final class DictionaryCard extends GatewayACard
{
    public function validateSettings(array $settings): array
    {
        return parent::validateSettings($settings) === [] ? [] : ['hold' => 'webx-payments::panel.errors.amounts'];
    }
}
