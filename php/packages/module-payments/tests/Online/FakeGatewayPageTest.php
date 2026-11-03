<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Testing\FakeGatewayServer;
use WebxUi\Payments\Testing\FakeGatewaySite;

/**
 * The page of «gateway A» (spec §21.2): where /pay/go sends the buyer, with the banner and the
 * buttons; it takes the form a `form` type hands off as a gateway's page would; and it is not there
 * at all without the switch, or in production.
 */
final class FakeGatewayPageTest extends OnlineCase
{
    #[Test]
    public function the_page_has_the_banner_and_the_buttons_of_its_type(): void
    {
        $card = $this->opened('card');

        $this->get(route(FakeGatewaySite::PAGE, ['uuid' => $card->uuid]))
            ->assertOk()
            ->assertSee('Test gateway — no money moves')
            ->assertSee('49.99 EUR')
            ->assertSeeInOrder(['Pay', 'Decline', 'Cancel', 'Pay, notify later', 'Repeat the notification', 'Bad signature', 'Foreign payment'])
            ->assertDontSee('Approve');

        // Like a real gateway's page: declined, the buyer goes back to the shop.
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $card->uuid, 'action' => 'decline']))->assertRedirect(route('webx.payments.return', ['uuid' => $card->uuid]));
        $this->assertSame('declined', $this->server()->payment($card->uuid)['status'] ?? null);
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $card->uuid, 'action' => 'approve']))->assertNotFound();
    }

    #[Test]
    public function the_form_of_a_form_type_opens_the_payment_at_the_gateway(): void
    {
        $payment = $this->begun('form');
        $page = (string) $this->get($this->app->make(PaymentLinks::class)->go($payment))->getContent();
        preg_match('/name="data" value="([^"]+)"/', $page, $data);
        preg_match('/name="signature" value="([^"]+)"/', $page, $signature);

        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'open']), ['data' => html_entity_decode($data[1]), 'signature' => 'forged'])->assertForbidden();
        $this->assertNull($this->server()->payment($payment->uuid));

        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'open']), ['data' => html_entity_decode($data[1]), 'signature' => html_entity_decode($signature[1])])
            ->assertRedirect(route(FakeGatewaySite::PAGE, ['uuid' => $payment->uuid]));
        $state = $this->app->make(FakeGatewayServer::class)->payment($payment->uuid);
        $this->assertNotNull($state);
        $this->assertSame(['form', 'wait', 4999], [$state['type'], $state['status'], $state['amount']]);
        $this->get(route(FakeGatewaySite::PAGE, ['uuid' => $payment->uuid]))->assertSee('Approve');
    }

    #[Test]
    public function without_the_switch_or_in_production_there_is_no_gateway_a(): void
    {
        $card = $this->opened('card');

        $this->app['config']->set('webx-payments.fake_gateway', false);
        $this->get(route(FakeGatewaySite::PAGE, ['uuid' => $card->uuid]))->assertNotFound();

        $this->app['config']->set('webx-payments.fake_gateway', true);
        $this->app['env'] = 'production';
        $this->get(route(FakeGatewaySite::PAGE, ['uuid' => $card->uuid]))->assertNotFound();
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $card->uuid, 'action' => 'pay']))->assertNotFound();
        $this->assertFalse(FakeGatewaySite::on($this->app));
    }

    private function begun(string $kind): Payment
    {
        $this->post($this->app->make(PaymentLinks::class)->attempts($this->onlineOrder($kind)));

        return Payment::query()->latest('id')->firstOrFail();
    }

    private function opened(string $kind): Payment
    {
        $payment = $this->begun($kind);
        $this->get($this->app->make(PaymentLinks::class)->go($payment));
        $this->assertNotNull($this->server()->payment($payment->uuid), 'Gateway A knows the payment.');
        $this->assertSame(FakeGatewayServer::HOST, 'gateway-a.test');

        return $payment;
    }
}
