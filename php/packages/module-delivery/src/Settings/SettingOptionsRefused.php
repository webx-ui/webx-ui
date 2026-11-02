<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Settings;

use WebxUi\Delivery\DeliveryException;

/**
 * The options of a field cannot be asked for: the carrier has none from its account, there is no
 * such field, no key is set, or a field it depends on is not chosen yet. Words for the panel.
 */
final class SettingOptionsRefused extends DeliveryException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
