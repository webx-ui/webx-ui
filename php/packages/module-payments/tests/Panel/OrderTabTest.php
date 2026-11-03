<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Orders\Panel\OrdersModule;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\SettleResult;
use WebxUi\Payments\Testing\FakeGateway;

/**
 * The tab «Payment» of the order's card (spec §13.4): patched onto `orders.show` for whoever reads
 * the payments; the method, the state, the attempts with their events by time; the actions are the
 * order's — `orders.manage` — and without it there is no link to pay and no button that works.
 */
final class OrderTabTest extends PanelCase
{
    #[Test]
    public function the_tab_is_on_the_card_for_whoever_reads_the_payments(): void
    {
        $tabs = $this->app->make(ScreenRegistry::class)->tree(OrdersModule::SCREEN);
        $payment = $this->find($tabs, 'payment');

        $this->assertNotNull($payment);
        $this->assertSame(['wx-tab', 'payments.view', 'wx-payments-order'], [$payment['type'], $payment['can'], $payment['children'][0]['type']]);
    }

    #[Test]
    public function an_order_paid_online_shows_its_attempt_with_the_events_by_time(): void
    {
        [$order, $payment] = $this->attemptAtGateway('card');
        $this->server()->act($payment->uuid, 'pay');
        $this->app->make(Payments::class)->check($payment->refresh(), 'cli');
        $this->signIn(['payments.view']);

        $tab = $this->getJson($this->api('orders/'.$order->id))->assertOk();
        $this->assertStringNotContainsString(FakeGateway::SECRET, $tab->getContent());
        $tab->assertJsonPath('data.state', 'paid')
            ->assertJsonPath('data.block.method', 'gateway-a-card')
            ->assertJsonPath('data.attempts.0.status', 'paid')
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.pay_link', null)
            ->assertJsonPath('data.bodies', false);

        $events = $tab->json('data.attempts.0.events');
        $this->assertSame(
            PaymentGatewayEvent::query()->where('payment_id', $payment->id)->orderBy('id')->pluck('kind')->all(),
            array_column($events, 'kind'),
        );
        $this->assertArrayNotHasKey('request_body', $events[0]);
    }

    #[Test]
    public function the_cards_mark_as_paid_is_the_payments_own_mark(): void
    {
        $order = $this->transferOrder();
        $this->signIn(['payments.view', 'orders.view', 'orders.manage']);

        // The button in the head of the card: the orders' route, one action with the tab's.
        $this->postJson('/api/cms/orders/'.$order->id.'/milestones', ['milestone' => 'paid', 'reference' => 'PD-0009', 'paid_at' => '2026-11-02', 'note' => 'By phone'])->assertOk();

        $payment = Payment::query()->where('order_id', $order->id)->sole();
        $this->assertSame([PaymentStatus::Paid, 'panel', 'PD-0009', '2026-11-02'], [$payment->status, $payment->source, $payment->meta['reference'] ?? null, $payment->paid_at?->toDateString()]);
        $this->assertNotNull($payment->admin_id);
        $event = PaymentGatewayEvent::query()->where('payment_id', $payment->id)->sole();
        $this->assertSame(['internal', 'manual', 'applied'], [$event->direction, $event->kind, $event->outcome]);
        $this->assertSame('PD-0009', $order->refresh()->milestones()->where('milestone', 'paid')->value('reference'));

        // The tab says how and by whom; the journal's «admin #N» reads as a name.
        $tab = $this->getJson($this->api('orders/'.$order->id))->assertOk()
            ->assertJsonPath('data.paid_by.reference', 'PD-0009')
            ->assertJsonPath('data.paid_by.method_title', 'Bank transfer');
        $this->assertNotNull($tab->json('data.paid_by.admin_name'));
        $this->assertStringNotContainsString('admin #', (string) $tab->json('data.attempts.0.events.0.message'));

        // A second press is the first mark: no second attempt.
        $this->postJson('/api/cms/orders/'.$order->id.'/milestones', ['milestone' => 'paid'])->assertOk();
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());
    }

    #[Test]
    public function a_reader_cannot_act_and_a_manager_of_orders_can(): void
    {
        $order = $this->transferOrder();
        $this->signIn(['payments.view']);

        $this->getJson($this->api('orders/'.$order->id))->assertOk()
            ->assertJsonPath('data.can_mark_paid', false)
            ->assertJsonPath('data.block.instructions.0.text', 'Shop A Ltd');
        $this->postJson($this->api('orders/'.$order->id.'/mark-paid'))->assertForbidden();
        $this->postJson($this->api('orders/'.$order->id.'/check'))->assertForbidden();
        $this->postJson($this->api('orders/'.$order->id.'/method'), ['method' => 'gateway-a-card'])->assertForbidden();

        $this->signIn(['payments.view', 'orders.view', 'orders.manage']);
        $tab = $this->getJson($this->api('orders/'.$order->id))->assertOk()
            ->assertJsonPath('data.state', 'awaiting')
            ->assertJsonPath('data.can_mark_paid', true)
            ->assertJsonPath('data.can_switch', true);
        $this->assertStringContainsString('/pay/', (string) $tab->json('data.pay_link'));
        $this->assertContains('gateway-a-card', array_column($tab->json('data.methods'), 'key'));

        $this->postJson($this->api('orders/'.$order->id.'/mark-paid'), ['reference' => 'PD-0007', 'paid_at' => '2026-11-02'])->assertOk()
            ->assertJsonPath('data.state', 'paid')
            ->assertJsonPath('data.attempts.0.reference', 'PD-0007')
            ->assertJsonPath('data.attempts.0.events.0.kind', 'manual')
            ->assertJsonPath('data.pay_link', null);

        // Paid already: refused with the words of the service.
        $this->postJson($this->api('orders/'.$order->id.'/mark-paid'))->assertUnprocessable()->assertJsonValidationErrors('order');
    }

    #[Test]
    public function the_method_is_switched_and_a_problem_resolved_by_the_manager(): void
    {
        $order = $this->transferOrder();
        $this->signIn(['payments.view', 'orders.view', 'orders.manage']);

        $this->postJson($this->api('orders/'.$order->id.'/method'), ['method' => 'gateway-a-card'])->assertOk()
            ->assertJsonPath('data.block.method', 'gateway-a-card')
            ->assertJsonPath('data.attempts.0.status', 'created');
        $this->postJson($this->api('orders/'.$order->id.'/method'), ['method' => 'nothing'])->assertUnprocessable()->assertJsonValidationErrors('method');

        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();
        $payment->forceFill(['status' => PaymentStatus::Paid, 'problem' => SettleResult::DUPLICATE])->save();

        $this->getJson($this->api('orders/'.$order->id))->assertJsonPath('data.attempts.0.can_resolve', true);
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/resolve'), ['note' => 'Refunded by hand'])->assertOk()
            ->assertJsonPath('data.attempts.0.resolved', true)
            ->assertJsonPath('data.attempts.0.can_resolve', false);
        $this->assertSame('Refunded by hand', $payment->refresh()->meta['resolved_note'] ?? null);
    }

    #[Test]
    public function check_with_the_gateway_asks_about_the_last_unfinished_attempt(): void
    {
        [$order, $payment] = $this->attemptAtGateway('card');
        $this->server()->act($payment->uuid, 'pay');
        $this->signIn(['payments.view', 'orders.view', 'orders.manage']);

        $this->getJson($this->api('orders/'.$order->id))->assertJsonPath('data.can_check', true);
        $this->postJson($this->api('orders/'.$order->id.'/check'))->assertOk()->assertJsonPath('data.state', 'paid');
        $this->postJson($this->api('orders/'.$order->id.'/check'))->assertUnprocessable();

        $this->postJson($this->api('attempts/'.$payment->id.'/check'))->assertOk()->assertJsonPath('data.status', 'paid');
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>|null
     */
    private function find(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if (($node['id'] ?? null) === $id) {
                return $node;
            }

            $found = $this->find($node['children'] ?? [], $id);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
