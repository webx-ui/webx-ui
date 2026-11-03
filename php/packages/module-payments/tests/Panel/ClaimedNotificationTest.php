<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Panel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Testing\FakeGatewaySite;

/**
 * «Bad signature» on the page of gateway A (spec §21.2; decision 56): the notification is
 * rejected and the status stays, but the event names the attempt and the order the body claimed
 * — the column «Order» of the journal and the red mark on the order's tab.
 */
final class ClaimedNotificationTest extends PanelCase
{
    /** @return iterable<string, array{string}> */
    public static function signed(): iterable
    {
        yield 'card — a signed JSON' => ['card'];
        yield 'form — a signed form' => ['form'];
    }

    #[Test]
    #[DataProvider('signed')]
    public function a_bad_signature_from_the_page_is_linked_to_the_order(string $kind): void
    {
        [$order, $payment] = $this->attemptAtGateway($kind);

        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'bad-signature']))->assertRedirect();

        $event = PaymentGatewayEvent::query()->where('direction', 'in')->where('kind', 'notify')->latest('id')->firstOrFail();
        $this->assertSame(['rejected', 'invalid'], [$event->outcome, $event->signature]);
        $this->assertSame([$payment->id, $order->id], [$event->payment_id, $event->order_id]);
        $this->assertNotSame(PaymentStatus::Paid, $payment->refresh()->status);

        $this->signIn(['payments.view']);
        $tab = $this->getJson($this->api('orders/'.$order->id))->assertOk();
        /** @var list<array<string, mixed>> $events */
        $events = $tab->json('data.attempts.0.events');
        $claimed = collect($events)->firstWhere('id', $event->id);
        $this->assertTrue($claimed['claimed'] ?? null);

        /** @var list<array<string, mixed>> $lines */
        $lines = $this->getJson($this->api('exchange?signature=1'))->assertOk()->json('data.data');
        $line = collect($lines)->firstWhere('id', $event->id);
        $this->assertSame($order->number, $line['order_number'] ?? null);
    }
}
