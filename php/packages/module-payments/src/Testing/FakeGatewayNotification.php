<?php

declare(strict_types=1);

namespace WebxUi\Payments\Testing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * «Pay, notify later» of gateway A (spec §21.2): the buyer is back at once, the notification comes
 * from the queue some seconds after — the return settles by the API, the late notification is `same`.
 */
final class FakeGatewayNotification implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public const int DELAY_SECONDS = 30;

    public function __construct(public readonly string $uuid) {}

    public function handle(Container $app, FakeGatewayNotifier $notifier): void
    {
        if (FakeGatewaySite::on($app)) {
            $notifier->send($this->uuid);
        }
    }
}
