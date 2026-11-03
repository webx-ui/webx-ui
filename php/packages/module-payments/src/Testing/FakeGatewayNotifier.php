<?php

declare(strict_types=1);

namespace WebxUi\Payments\Testing;

use Symfony\Component\HttpFoundation\Response;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Online\Notifications;
use WebxUi\Payments\Online\PaymentLinks;

/**
 * «Gateway A» posts a notification to the site (spec §21.2): the raw body and headers of its type,
 * exactly as a real gateway's would come, handed to the same `Notifications` the route
 * POST /payments/notify/{method} calls — so the journal writes it by the same path of §9.2,
 * without the site calling itself over the network.
 */
final readonly class FakeGatewayNotifier
{
    public function __construct(
        private FakeGatewayServer $server,
        private Notifications $notifications,
        private PaymentLinks $links,
    ) {}

    /** @param  string  $variant  'valid' | 'signature' | 'amount' | 'status' | 'foreign' | 'unrelated' */
    public function send(string $uuid, string $variant = 'valid'): ?Response
    {
        $state = $this->server->payment($uuid);

        if ($state === null) {
            return null;
        }

        $method = $this->methodKey($uuid, (string) $state['type']);

        // A type that gives no address of notifications (`unsigned`) has it in its cabinet: the method's own.
        if (! is_string($state['notify_url'] ?? null)) {
            $this->server->put([...$state, 'notify_url' => $this->links->notifyUrl($method)]);
        }

        return $this->notifications->receive($this->server->notification($uuid, $variant), $method);
    }

    private function methodKey(string $uuid, string $type): string
    {
        $payment = Payment::query()->where('uuid', $uuid)->first();

        return $payment !== null ? $payment->method_key : FakeGatewaySite::METHODS['gateway-a.'.$type];
    }
}
