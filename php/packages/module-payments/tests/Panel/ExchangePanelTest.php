<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Panel;

use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Testing\FakeGateway;

/**
 * The exchange journal of the panel (spec §13.2, §20.7): a line is never a body; the headers and
 * bodies of one event come only with `payments.exchange` (decision 47), «Download» is an attachment
 * that never runs; the filters find the bad signature; and no route changes or removes an event.
 */
final class ExchangePanelTest extends PanelCase
{
    #[Test]
    public function a_line_has_no_body_and_one_event_shows_bodies_only_with_the_right(): void
    {
        [$order, $payment] = $this->attemptAtGateway('card');
        $this->signIn(['payments.view']);

        $list = $this->getJson($this->api('exchange'))->assertOk();
        $this->assertNotEmpty($list->json('data.data'));
        $this->assertStringNotContainsString(FakeGateway::SECRET, $list->getContent());

        foreach ($list->json('data.data') as $line) {
            $this->assertArrayNotHasKey('request_body', $line);
            $this->assertArrayNotHasKey('request_headers', $line);
        }

        $start = PaymentGatewayEvent::query()->where('kind', 'start')->firstOrFail();
        $this->getJson($this->api('exchange/'.$start->id))->assertOk()
            ->assertJsonPath('data.bodies', false)
            ->assertJsonPath('data.request', null)
            ->assertJsonPath('data.response', null)
            ->assertJsonPath('data.order_number', $order->number)
            ->assertJsonPath('data.payment_uuid', $payment->uuid);
        $this->get($this->api('exchange/'.$start->id.'/request'))->assertForbidden();

        $this->signIn(['payments.view', 'payments.exchange']);
        $shown = $this->getJson($this->api('exchange/'.$start->id))->assertOk()->assertJsonPath('data.bodies', true);
        $this->assertSame($start->request_body, $shown->json('data.request.body'));
        $this->assertNotEmpty($shown->json('data.trace_events'));
        $this->assertStringNotContainsString(FakeGateway::SECRET, $shown->getContent());

        $download = $this->get($this->api('exchange/'.$start->id.'/request'))->assertOk();
        $this->assertSame($start->request_body, $download->getContent());
        $this->assertStringStartsWith('attachment;', (string) $download->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $download->headers->get('X-Content-Type-Options'));
    }

    #[Test]
    public function the_filters_find_the_bad_signature_the_order_and_the_attempt_a_page_at_a_time(): void
    {
        [$order, $payment] = $this->attemptAtGateway('card');
        $this->server()->act($payment->uuid, 'pay');
        $bad = $this->server()->notification($payment->uuid, 'signature');
        $this->call('POST', $this->app->make(PaymentLinks::class)->notifyUrl($payment->method_key), [], [], [], $bad->server->all(), $bad->getContent())->assertStatus(400);
        $this->signIn(['payments.view']);

        $signature = $this->getJson($this->api('exchange?signature=1'))->assertOk()->json('data.data');
        $this->assertCount(1, $signature);
        $this->assertSame(['notify', 'invalid', 'rejected', true], [$signature[0]['kind'], $signature[0]['signature'], $signature[0]['outcome'], $signature[0]['error']]);
        $this->assertCount(1, $this->getJson($this->api('exchange?errors=1&kind=notify'))->json('data.data'));

        $ofOrder = $this->getJson($this->api('exchange?order='.$order->number))->json('data.data');
        $this->assertNotEmpty($ofOrder);
        $this->assertSame([$order->id], array_values(array_unique(array_column($ofOrder, 'order_id'))));
        $this->assertSame(count($ofOrder), count($this->getJson($this->api('exchange?order_id='.$order->id))->json('data.data')));
        $this->assertNotEmpty($this->getJson($this->api('exchange?payment='.$payment->uuid))->json('data.data'));
        $this->assertSame([], $this->getJson($this->api('exchange?order=NOPE'))->json('data.data'));

        // Keyset: the page after the last id is the older events, none twice.
        $all = PaymentGatewayEvent::query()->count();
        $first = $this->getJson($this->api('exchange?per_page=10'))->json('data');
        $seen = array_column($first['data'], 'id');

        for ($next = $first['next']; $next !== null;) {
            $page = $this->getJson($this->api('exchange?per_page=10&before='.$next))->json('data');
            $seen = [...$seen, ...array_column($page['data'], 'id')];
            $next = $page['next'];
        }

        $this->assertCount($all, array_unique($seen));
    }

    #[Test]
    public function no_route_of_the_panel_writes_or_removes_an_event(): void
    {
        $routes = collect($this->app->make('router')->getRoutes()->getRoutes())
            ->filter(static fn (Route $route): bool => str_contains($route->uri(), 'payments/exchange'));

        $this->assertNotEmpty($routes);
        $this->assertSame([['GET', 'HEAD']], $routes->map(static fn (Route $route): array => $route->methods())->unique()->values()->all());
    }
}
