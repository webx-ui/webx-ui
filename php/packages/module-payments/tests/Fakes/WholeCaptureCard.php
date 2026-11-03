<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Fakes;

use WebxUi\Payments\Testing\GatewayACard;

/** Gateway A's card, as a gateway that captures only the whole hold (decision 52). */
final class WholeCaptureCard extends GatewayACard
{
    public function capturesPartially(): bool
    {
        return false;
    }
}
