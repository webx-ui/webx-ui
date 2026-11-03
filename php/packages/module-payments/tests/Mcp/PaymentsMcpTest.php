<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Mcp;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Registry\BoundTool;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Mcp\PaymentsResources;
use WebxUi\Payments\Mcp\PaymentsTools;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\SettleResult;
use WebxUi\Payments\Testing\FakeGateway;

/**
 * The payments for an agent (spec §16, §23 П7): the same answers as the panel, by the same doors;
 * the exchange journal read only, its bodies behind `payments.exchange`; a dry run of every action
 * writes nothing; and no key of a gateway in any answer.
 */
final class PaymentsMcpTest extends McpCase
{
    #[Test]
    public function the_methods_say_which_keys_are_set_and_never_what_they_are(): void
    {
        $methods = $this->use('payments_methods_list')['methods'];
        $card = array_column($methods, null, 'key')['gateway-a-card'] ?? null;

        $this->assertNotNull($card);
        $this->assertSame(['secret_key: set'], $card['credentials']);
        $this->assertSame('set', $card['credentials_state']);
        $this->assertSame('gateway-a', $card['gateway']);
        $this->assertStringContainsString('/payments/notify/gateway-a-card', (string) $card['notify_url']);
        $this->assertSame('any', $card['delivery_rule']['mode']);

        // The panel's list says the same of each method.
        $this->signIn(['payments.view']);
        $panel = array_column((array) $this->getJson($this->api('methods'))->json('data.methods'), null, 'key');

        foreach ($methods as $method) {
            $this->assertSame($panel[$method['key']]['title'], $method['title']);
            $this->assertSame($panel[$method['key']]['enabled'], $method['enabled']);
            $this->assertSame($panel[$method['key']]['credentials'], $method['credentials_state']);
        }

        $this->assertNoSecrets();
    }

    #[Test]
    public function a_method_is_saved_by_the_panels_form_and_its_keys_are_refused(): void
    {
        $this->refused('payments_methods_save', ['key' => 'gateway-a-card', 'values' => ['credentials' => ['secret_key' => 'typed-in-a-chat-0001']]], 'Gateway keys are set in the panel only.');
        $this->refused('payments_methods_save', ['key' => 'gateway-a-card', 'values' => ['settings' => ['secret_key' => 'typed-in-a-chat-0001']]], 'Gateway keys are set in the panel only.');
        $this->assertSame(FakeGateway::SECRET, PaymentMethod::query()->where('key', 'gateway-a-card')->value('credentials')['secret_key'] ?? null);

        $dry = $this->use('payments_methods_save', ['key' => 'bank-transfer', 'values' => ['titles' => 'Wire', 'enabled' => true], 'dry_run' => true]);
        $this->assertSame(['Wire', true, true], [$dry['method']['title'], $dry['method']['enabled'], $dry['dry_run']]);
        $this->assertFalse(PaymentMethod::query()->where('key', 'bank-transfer')->value('enabled'));

        $saved = $this->use('payments_methods_save', ['key' => 'bank-transfer', 'values' => [
            'titles' => ['en' => 'Wire'],
            'enabled' => true,
            'min_amount' => 10,
            'settings' => ['recipient' => ['en' => 'Shop A Ltd']],
        ]]);
        $this->assertSame([1000, 'Shop A Ltd'], [$saved['method']['min_amount'], $saved['method']['settings']['recipient']['en'] ?? null]);
        $this->assertTrue(PaymentMethod::query()->where('key', 'bank-transfer')->value('enabled'));

        $new = $this->use('payments_methods_save', ['type' => 'store.bank_transfer', 'values' => ['titles' => 'Second account']]);
        $this->assertTrue($new['created']);
        $this->assertFalse($new['method']['enabled']);
        $this->refused('payments_methods_save', ['type' => 'nothing.here', 'values' => []], 'A new method needs `type`');
        $this->refused('payments_methods_save', ['key' => 'bank-transfer', 'values' => ['colour' => 'red']], 'colour');
        $this->assertNoSecrets();
    }

    #[Test]
    public function another_sections_right_is_refused_reads_and_writes(): void
    {
        $this->agent('payments_methods_list', [], ['delivery.view'])->assertHasErrors(['[payments.view]']);
        $this->agent('payments_list', [], ['delivery.view'])->assertHasErrors(['[payments.view]']);
        $this->agent('payments_exchange_list', [], ['delivery.view'])->assertHasErrors(['[payments.view]']);
        $this->agent('payments_methods_reorder', ['methods' => []], ['delivery.view'])->assertHasErrors(['[payments.manage]']);
        $this->agent('payments_methods_reorder', ['methods' => []], ['payments.view'])->assertHasErrors(['[payments.manage]']);
        $this->agent('payments_methods_list', [], ['payments.view'])->assertOk();
    }

    #[Test]
    public function the_methods_are_put_in_order_by_key(): void
    {
        $first = PaymentMethod::query()->orderByDesc('position')->value('key');

        $dry = $this->use('payments_methods_reorder', ['methods' => [$first], 'dry_run' => true]);
        $this->assertSame($first, $dry['methods'][0]);
        $this->assertNotSame($first, PaymentMethod::query()->orderBy('position')->value('key'));

        $this->use('payments_methods_reorder', ['methods' => [$first]]);
        $this->assertSame($first, PaymentMethod::query()->orderBy('position')->orderBy('id')->value('key'));
        $this->refused('payments_methods_reorder', ['methods' => ['nothing']], 'No such method: nothing');
    }

    #[Test]
    public function the_trial_says_of_every_method_whether_it_is_offered_and_why_not(): void
    {
        PaymentMethod::query()->where('key', 'gateway-a-card')->update(['max_amount' => 1000]);

        $answer = $this->use('payments_available', ['amount' => 4999, 'currency' => 'EUR']);
        $byKey = array_column($answer['methods'], null, 'key');

        $this->assertFalse($byKey['gateway-a-card']['available']);
        $this->assertSame('amount_above_max', $byKey['gateway-a-card']['reason']);
        $this->assertNotEmpty($byKey['gateway-a-card']['reason_text']);
        $this->assertTrue($byKey['gateway-a-form']['available']);

        $nothing = $this->use('payments_available', ['amount' => 0, 'currency' => 'EUR'])['methods'];
        $this->assertSame(['nothing_to_pay'], array_values(array_unique(array_column($nothing, 'reason'))));

        $cart = $this->cart();
        $ofCart = $this->use('payments_available', ['cart_id' => $cart->cart->id]);
        $this->assertGreaterThan(0, $ofCart['amount']);
        $this->refused('payments_available', ['cart_id' => 999999], 'There is no cart 999999');
    }

    #[Test]
    public function an_order_reads_as_its_tab_and_an_attempt_as_its_window(): void
    {
        [$order, $payment] = $this->attemptAtGateway('card');

        $mcp = $this->use('payments_get', ['order' => $order->number]);
        $this->signIn(['payments.view', 'payments.manage', 'payments.exchange', 'orders.view', 'orders.manage']);
        $panel = $this->getJson($this->api('orders/'.$order->id))->assertOk()->json('data');

        $this->assertEquals($this->plain($panel), $this->plain($mcp));
        $this->assertSame([$payment->uuid], array_column($mcp['attempts'], 'uuid'));

        $one = $this->use('payments_get', ['payment' => $payment->uuid]);
        $window = $this->getJson($this->api('attempts/'.$payment->id))->json('data');
        $this->assertSame(array_column($window['events'], 'id'), array_column($one['events'], 'id'));
        $this->assertSame($window['status'], $one['status']);

        $list = $this->use('payments_list', ['search' => $order->number]);
        $this->assertSame([$payment->uuid], array_column($list['attempts'], 'uuid'));
        $this->assertSame([], $this->use('payments_list', ['tab' => 'failed'])['attempts']);
        $this->assertNoSecrets();
    }

    #[Test]
    public function the_journal_is_the_panels_without_bodies_and_one_event_has_them_with_the_right(): void
    {
        [$order] = $this->attemptAtGateway('card');

        $mcp = $this->use('payments_exchange_list', ['order' => $order->number]);
        $this->signIn(['payments.view']);
        $panel = $this->getJson($this->api('exchange?order='.$order->number))->json('data');
        $this->assertSame(array_column($panel['data'], 'id'), array_column($mcp['events'], 'id'));
        $this->assertEquals($this->plain($panel['data']), $this->plain($mcp['events']));

        foreach ($mcp['events'] as $line) {
            $this->assertArrayNotHasKey('request', $line);
            $this->assertArrayNotHasKey('request_body', $line);
        }

        $start = PaymentGatewayEvent::query()->where('kind', 'start')->firstOrFail();
        $event = $this->use('payments_exchange_get', ['event' => $start->id]);
        $this->assertSame([$start->request_body, 'utf-8', false], [$event['request']['body'], $event['request']['encoding'], $event['request']['cut']]);
        $this->assertSame($start->request_sha256, $event['request']['sha256']);
        $this->assertSame('Gateway A (test)', $event['gateway_title'] ?? null);

        $trace = $this->use('payments_exchange_get', ['trace' => $start->trace]);
        $this->assertContains($start->id, array_column($trace['events'], 'id'));

        // The bodies are the right payments.exchange (decision 47): not even listed without it.
        $this->agent('payments_exchange_get', ['event' => $start->id], ['payments.view'])->assertHasErrors();
        $this->assertNoSecrets();
    }

    #[Test]
    public function a_body_over_the_limit_is_cut_for_the_agent_and_a_binary_one_is_base64(): void
    {
        [, $payment] = $this->attemptAtGateway('card');
        $start = PaymentGatewayEvent::query()->where('kind', 'start')->firstOrFail();
        // Written past the model, which refuses: a test stands in for a gateway's long answer.
        $long = str_repeat('é', PaymentsTools::BODY_SHOWN);
        $binary = "\xFF\xFE".random_bytes(32);
        $this->app->make('db')->table('payment_gateway_events')->where('id', $start->id)->update(['response_body' => $long, 'request_body' => $binary]);

        $event = $this->use('payments_exchange_get', ['event' => $start->id]);

        $this->assertSame(['utf-8', true], [$event['response']['encoding'], $event['response']['cut']]);
        $this->assertLessThanOrEqual(PaymentsTools::BODY_SHOWN, strlen($event['response']['body']));
        $this->assertTrue(mb_check_encoding($event['response']['body'], 'UTF-8'));
        $this->assertSame(['base64', $binary], [$event['request']['encoding'], base64_decode($event['request']['body'], true)]);
        $this->assertSame($payment->id, $event['payment_id']);
    }

    #[Test]
    public function no_tool_changes_or_removes_an_event_of_the_journal(): void
    {
        $tools = array_values(array_filter(
            $this->app->make(ToolRegistry::class)->tools(),
            static fn (BoundTool $tool): bool => $tool->moduleId === 'payments',
        ));
        $names = array_map(static fn (BoundTool $tool): string => $tool->fullName(), $tools);

        $this->assertContains('payments_exchange_list', $names);
        $this->assertContains('payments_exchange_get', $names);

        foreach ($tools as $tool) {
            if (str_starts_with($tool->fullName(), 'payments_exchange')) {
                $this->assertFalse($tool->tool->mutating, $tool->fullName().' writes.');
            }

            $this->assertDoesNotMatchRegularExpression('/(delete|remove|prune|purge|edit|update)/', $tool->fullName());
        }
    }

    #[Test]
    public function marking_paid_by_the_agent_is_the_cards_mark_and_a_dry_run_writes_nothing(): void
    {
        $order = $this->transferOrder();
        $events = PaymentGatewayEvent::query()->count();

        $dry = $this->use('payments_mark_paid', ['order' => $order->number, 'reference' => 'PD-0007', 'dry_run' => true]);
        $this->assertSame(['paid', 'internal/manual', true, 4999], [$dry['milestone'], $dry['journal'], $dry['closes']['awaiting_payment'], $dry['attempt']['amount']]);
        $this->assertNull($order->refresh()->paid_at);
        $this->assertSame(0, Payment::query()->where('order_id', $order->id)->count());
        $this->assertSame($events, PaymentGatewayEvent::query()->count());

        $done = $this->use('payments_mark_paid', ['order' => $order->number, 'reference' => 'PD-0007', 'paid_at' => '2026-11-02']);
        $this->assertSame(['paid', 'PD-0007', 'mcp'], [$done['attempt']['status'], $done['attempt']['reference'], $done['attempt']['source']]);
        $this->assertSame(['internal/manual'], array_map(static fn (array $e): string => $e['direction'].'/'.$e['kind'], $done['attempt']['events']));
        $this->assertFalse($done['awaiting_payment']);
        $this->assertSame('2026-11-02', $order->refresh()->paid_at?->toDateString());

        $this->refused('payments_mark_paid', ['order' => $order->number], "Order {$order->number}:");
        $this->refused('payments_mark_paid', ['order' => 'NOPE'], 'There is no order NOPE');
    }

    #[Test]
    public function checking_with_the_gateway_asks_it_and_a_dry_run_applies_nothing(): void
    {
        [$order, $payment] = $this->attemptAtGateway('card');
        $this->server()->act($payment->uuid, 'pay');
        $asked = PaymentGatewayEvent::query()->where('kind', 'status')->count();

        $dry = $this->use('payments_check', ['order' => $order->number, 'dry_run' => true]);
        $this->assertSame(['paid', 'pending', 'paid', true], [$dry['reported'], $dry['status_before'], $dry['status_after'], $dry['dry_run']]);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertNull($order->refresh()->paid_at);
        // The gateway was asked all the same: every exchange is in the journal.
        $this->assertSame($asked + 1, PaymentGatewayEvent::query()->where('kind', 'status')->count());

        $done = $this->use('payments_check', ['payment' => $payment->uuid]);
        $this->assertSame(['applied', 'paid'], [$done['outcome'], $done['payment']['status']]);
        $this->assertNotNull($order->refresh()->paid_at);

        $this->refused('payments_check', ['payment' => $payment->uuid], 'nothing to ask the gateway');
    }

    #[Test]
    public function another_method_for_the_order_and_its_pay_link(): void
    {
        $order = $this->transferOrder();

        $dry = $this->use('payments_switch_method', ['order' => $order->number, 'method' => 'gateway-a-card', 'dry_run' => true]);
        $this->assertSame(['gateway-a-card', true, true], [$dry['block']['method'], $dry['new_attempt'], $dry['awaiting_payment']]);
        $this->assertSame('bank-transfer', $order->refresh()->payment['method'] ?? null);

        $done = $this->use('payments_switch_method', ['order' => $order->number, 'method' => 'gateway-a-card']);
        $this->assertSame(['gateway-a-card', 'created'], [$done['block']['method'], $done['attempt']['status']]);
        $this->assertStringContainsString('/pay/', (string) $done['pay_url']);
        $this->refused('payments_switch_method', ['order' => $order->number, 'method' => 'nothing'], 'is not an enabled method');

        $link = $this->use('payments_pay_link', ['order' => $order->number]);
        $this->assertSame([4999, $this->app->make(PaymentLinks::class)->page($order->refresh())], [$link['due'], $link['url']]);

        $this->app->make(Payments::class)->markPaid($order, 'panel');
        $paid = $this->use('payments_pay_link', ['order' => $order->number]);
        $this->assertSame([null, "Order {$order->number} is paid"], [$paid['url'], $paid['message']]);
    }

    #[Test]
    public function the_problems_are_summed_up_and_one_is_resolved_with_a_note(): void
    {
        [$order, $payment] = $this->attemptAtGateway('card');
        $payment->forceFill(['status' => PaymentStatus::Paid, 'problem' => SettleResult::DUPLICATE])->save();
        PaymentMethod::query()->where('key', 'gateway-a-form')->update(['credentials' => null]);
        Order::query()->whereKey($order->id)->update(['payment_failed_at' => now()]);

        $summary = $this->use('payments_problems');
        $this->assertSame(['duplicate', 1], [$summary['problems'][0]['problem'], $summary['problems'][0]['count']]);
        $this->assertContains('gateway-a-form', array_column($summary['methods_without_keys'], 'key'));
        $this->assertContains($order->number, array_column($summary['failed_orders'], 'number'));

        $this->use('payments_resolve_problem', ['payment' => $payment->uuid, 'note' => 'Given back', 'dry_run' => true]);
        $this->assertArrayNotHasKey('resolved_at', $payment->refresh()->meta ?? []);

        $done = $this->use('payments_resolve_problem', ['payment' => $payment->uuid, 'note' => 'Given back']);
        $this->assertTrue($done['payment']['resolved']);
        $this->assertSame([], $this->use('payments_problems')['problems']);
        $this->refused('payments_resolve_problem', ['payment' => $payment->uuid], "Payment {$payment->uuid}:");
    }

    #[Test]
    public function the_catalog_is_read_from_the_registries(): void
    {
        $resource = array_column($this->app->make(PaymentsResources::class)->all(), null, 'uri')['payments://catalog'] ?? null;
        $this->assertNotNull($resource);
        $catalog = ($resource->handler)();

        $types = array_column($catalog['types'], null, 'key');
        $this->assertTrue($types['store.bank_transfer']['awaits_payment']);
        $this->assertFalse($types['store.on_receipt']['awaits_payment']);
        $this->assertSame('gateway-a', $types['gateway-a.card']['gateway']);
        $this->assertTrue($types['gateway-a.card']['holds']);
        $this->assertFalse($types['gateway-a.unsigned']['trusts_notifications']);
        $this->assertSame(['paid'], array_column($catalog['statuses'], 'goes_to', 'status')['failed']);
        $this->assertContains('gateway-a-card', array_column($catalog['notify_urls'], 'method'));
        $this->assertContains('manual', $catalog['exchange']['kinds']);
    }

    /** No key of a gateway in anything the tools answered (§15.2, §19). */
    private function assertNoSecrets(): void
    {
        $this->assertNotSame('', $this->answered);
        $this->assertStringNotContainsString(FakeGateway::SECRET, $this->answered);
        $this->assertStringNotContainsString(json_encode(FakeGateway::SECRET), $this->answered);
    }

    /**
     * An answer as JSON reads it — the panel's went through JSON, the tool's through its own.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function plain(array $value): array
    {
        return json_decode((string) json_encode($value), true);
    }
}
