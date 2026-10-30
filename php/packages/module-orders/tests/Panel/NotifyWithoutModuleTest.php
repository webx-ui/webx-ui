<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Models\StatusChange;

/**
 * Without `webx-ui/module-notifications` a message is never sent by hand (decision 99): the card
 * offers neither «Send again» nor «Send the buyer a letter», and the routes say why and write nothing.
 */
final class NotifyWithoutModuleTest extends PanelTestCase
{
    #[Test]
    public function the_card_offers_nothing_and_the_routes_refuse(): void
    {
        $this->signIn();
        $order = $this->order();

        $card = $this->getJson($this->api($order->id))->assertOk()->json('data.order');
        $this->assertNull($card['notifications']);
        $this->assertSame([false, false, null], [$card['actions']['resend'], $card['actions']['notify'], $card['actions']['notify_reason']]);

        $refusal = __('webx-orders::panel.notify-refusals.unavailable');
        $this->assertSame('unavailable', $this->getJson($this->api($order->id.'/notify'))->assertOk()->json('data.reason'));
        $this->postJson($this->api($order->id.'/notify'), ['event' => 'orders.placed'])->assertStatus(422)->assertJsonPath('errors.event.0', $refusal);
        $this->postJson($this->api($order->id.'/deliveries/1/resend'))->assertStatus(422)->assertJsonPath('errors.delivery.0', $refusal);

        $this->assertSame(0, StatusChange::query()->where('kind', StatusChange::NOTIFICATION_SENT)->count());
    }
}
