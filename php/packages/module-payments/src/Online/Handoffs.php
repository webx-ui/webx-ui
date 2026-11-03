<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Orders;
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeDecision;
use WebxUi\Payments\Exchange\ExchangeEntry;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Exchange\ExchangeSecrets;
use WebxUi\Payments\Exchange\Outcome;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Handoff;
use WebxUi\Payments\MethodCredentials;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\PaymentStatus;

/**
 * GET /pay/go/{uuid} (spec §8.2): the one moment the base opens a payment at the gateway. Once per
 * attempt — an attempt that left `created` goes to the page of payment, so «Back» from the
 * gateway never opens a second payment; under the attempt's lock, so two tabs do not either.
 *
 * The satellite's requests are journaled by `GatewayHttp` (`out/start`); the handoff itself — the
 * redirect or the form the browser posts — is an event `browser/handoff` of the same trace. A
 * gateway that failed or did not answer makes the attempt `failed` and the order's «payment
 * failed», and the buyer reads «Could not open the payment» on the page of payment.
 */
final readonly class Handoffs
{
    public const string FLASH = 'webx-payments.error';

    public function __construct(
        private Methods $methods,
        private GatewayCalls $calls,
        private MethodCredentials $credentials,
        private StartRequests $requests,
        private PaymentLinks $links,
        private ExchangeJournal $journal,
        private Orders $orders,
        private Views $views,
        private Redirector $redirect,
    ) {}

    public function go(Payment $payment, Request $request): Response
    {
        if ($payment->status !== PaymentStatus::Created) {
            return $this->toPage($payment);
        }

        $lock = Cache::lock('webx-payments:'.$payment->uuid, 30);

        if (! $lock->get()) {
            return $this->toPage($payment);
        }

        try {
            $payment->refresh();

            if ($payment->status !== PaymentStatus::Created) {
                return $this->toPage($payment);
            }

            return $this->start($payment, $request);
        } finally {
            $lock->release();
        }
    }

    private function start(Payment $payment, Request $request): Response
    {
        /** @var Order $order */
        $order = $payment->order()->firstOrFail();
        $method = $this->methods->find($payment->method_key);
        $type = $this->calls->type($payment->type);

        if ($method === null || $type === null || $method->type !== $payment->type) {
            return $this->fail($payment, $order, 'The payment method is no longer available');
        }

        $credentials = $this->credentials->for($method);
        $trace = ExchangeContext::newTrace();
        $http = $this->calls->http($type, $method, $credentials, ExchangeKind::Start, $payment->source, $payment, $trace);
        $started = CarbonImmutable::now();

        try {
            $handoff = $type->start($method, $credentials, $this->requests->of($order, $payment, $type, $method), $http);
        } catch (GatewayUnavailable $e) {
            return $this->fail($payment, $order, $e->getMessage());
        } catch (ConnectionException) {
            return $this->fail($payment, $order, GatewayUnavailable::timeout()->getMessage());
        } catch (Throwable $e) {
            // A satellite's own failure: logged without bodies — they are in the journal (§15.2).
            Log::error('webx-payments: start failed', ['type' => $payment->type, 'payment' => $payment->uuid, 'error' => $e::class]);

            return $this->fail($payment, $order, 'The gateway could not open the payment');
        }

        $payment->forceFill([
            'status' => PaymentStatus::Pending,
            'gateway_ref' => $handoff->gatewayRef ?? $payment->gateway_ref,
            'expires_at' => $handoff->expiresAt,
        ])->save();

        $response = $this->respond($handoff, (string) $order->locale);
        $this->journalHandoff($payment, $request, $response, $trace, $http->secrets(), $http->context()->gateway, $started);

        return $response;
    }

    private function respond(Handoff $handoff, string $locale): Response
    {
        if (! $handoff->isPost()) {
            return new RedirectResponse($handoff->url, 302);
        }

        $html = $this->views->make('webx-payments::pay.handoff', [
            'url' => $handoff->url,
            'fields' => $handoff->fields,
            'locale' => $locale,
        ])->render();

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** The handoff as it went to the browser (§20.1): the address, or the form — for a form gateway that is the request itself. */
    private function journalHandoff(Payment $payment, Request $request, Response $response, string $trace, ExchangeSecrets $secrets, ?string $gateway, CarbonImmutable $started): void
    {
        $headers = $response->headers->allPreserveCaseWithoutCookies();
        $id = $this->journal->record(new ExchangeEntry(
            kind: ExchangeKind::Handoff,
            trace: $trace,
            gateway: $gateway,
            methodKey: $payment->method_key,
            paymentId: $payment->id,
            orderId: $payment->order_id,
            source: $payment->source,
            httpMethod: $request->getMethod(),
            url: $request->fullUrl(),
            ip: $request->ip(),
            responseStatus: $response->getStatusCode(),
            responseHeaders: $headers,
            responseBody: $response instanceof RedirectResponse ? null : (string) $response->getContent(),
            startedAt: $started,
            durationMs: (int) $started->diffInMilliseconds(CarbonImmutable::now(), true),
        ), $secrets);

        if ($id !== null) {
            $this->journal->decide($id, new ExchangeDecision(
                outcome: Outcome::Applied,
                paymentId: $payment->id,
                orderId: $payment->order_id,
                statusBefore: PaymentStatus::Created->value,
                statusAfter: PaymentStatus::Pending->value,
            ), $secrets);
        }
    }

    private function fail(Payment $payment, Order $order, string $reason): Response
    {
        $payment->forceFill(['status' => PaymentStatus::Failed, 'failure_reason' => mb_substr($reason, 0, 500)])->save();

        try {
            $this->orders->paymentFailed($order, 'payment:'.$payment->method_key, $reason);
        } catch (Throwable $e) {
            Log::warning('webx-payments: the order did not take «payment failed»', ['payment' => $payment->uuid, 'error' => $e::class]);
        }

        return $this->toPage($payment)->with(self::FLASH, (string) trans('webx-payments::buyer.pay.unavailable', [], (string) $order->locale));
    }

    private function toPage(Payment $payment): RedirectResponse
    {
        /** @var Order $order */
        $order = $payment->order()->firstOrFail();
        $page = $this->links->page($order);

        return $this->redirect->to($page !== '' ? $page : '/', 303);
    }
}
